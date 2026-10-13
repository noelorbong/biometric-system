<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class BiometricAutoCorrectionService
{
    /**
     * Auto-correct biometric punches according to the shift schedule.
     *
     * Reference: biometric_attendance_reference_with_review_candidates.pdf
     * Fixed 4-position format: Morning IN | Morning OUT | Afternoon IN | Afternoon OUT
     *
     * @param array $punches Array of punch items with 'time'/'checktime' and optional 'type'/'checktype'
     * @param array $schedules Array of shift schedule rows with 'sequence', 'time_in', 'time_out'
     * @param string|CarbonImmutable|null $baseDate The base date string 'Y-m-d' or CarbonImmutable instance
     * @param CarbonImmutable|null $now Optional cutoff time (punches after this are ignored)
     * @return array Corrected slots keyed by schedule index [0 => ['in' => ..., 'out' => ...], 1 => ...]
     */
    public function correct(array $punches, array $schedules, mixed $baseDate = null, ?CarbonImmutable $now = null): array
    {
        $dateStr = $baseDate instanceof CarbonImmutable
            ? $baseDate->format('Y-m-d')
            : ($baseDate ? substr((string) $baseDate, 0, 10) : null);

        // 1. Filter out invalid punches (Scenario 16) and filter by date if specified
        $validPunches = [];
        foreach ($punches as $p) {
            $rawTime = $p['time'] ?? $p['checktime'] ?? $p['CHECKTIME'] ?? null;
            if (!$rawTime) {
                continue;
            }

            try {
                $time = CarbonImmutable::parse($rawTime, $now?->timezone);
            } catch (\Throwable) {
                continue;
            }

            // Scenario 19: Keep punches on their own date
            if ($dateStr !== null && $time->format('Y-m-d') !== $dateStr) {
                continue;
            }

            // Exclude future punches if $now is given
            if ($now !== null && $time > $now) {
                continue;
            }

            $rawType = strtoupper((string) ($p['type'] ?? $p['checktype'] ?? $p['CHECKTYPE'] ?? ''));
            $validPunches[] = [
                'time_obj' => $time,
                'time' => $time->format('Y-m-d H:i:s'),
                'ts' => $time->timestamp,
                'time_str' => $time->format('h:i A'),
                'minutes' => ($time->hour * 60) + $time->minute,
                'type' => in_array($rawType, ['I', 'O'], true) ? $rawType : null,
                'raw_punch' => $p,
            ];
        }

        if (empty($validPunches) || empty($schedules)) {
            $empty = [];
            foreach (array_values($schedules) as $idx => $s) {
                $empty[$idx] = ['in' => null, 'out' => null, 'in_punch' => null, 'out_punch' => null];
            }
            return $empty;
        }

        // 2. Sort chronologically (Scenario 18)
        usort($validPunches, fn ($a, $b) => $a['ts'] <=> $b['ts']);

        // 3. Deduplicate exact same-timestamp punches (Scenario 17)
        $uniquePunches = [];
        foreach ($validPunches as $p) {
            $last = end($uniquePunches);
            if ($last && $last['time'] === $p['time']) {
                continue;
            }
            $uniquePunches[] = $p;
        }

        // Parse schedules
        $sortedSchedules = array_values($schedules);
        usort($sortedSchedules, fn ($a, $b) => ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0));

        $parsedSchedules = [];
        foreach ($sortedSchedules as $idx => $s) {
            $inParts = explode(':', (string) $s['time_in']);
            $outParts = explode(':', (string) $s['time_out']);
            $inMin = ((int) ($inParts[0] ?? 0) * 60) + (int) ($inParts[1] ?? 0);
            $outMin = ((int) ($outParts[0] ?? 0) * 60) + (int) ($outParts[1] ?? 0);
            $parsedSchedules[] = [
                'index' => $idx,
                'in_min' => $inMin,
                'out_min' => $outMin,
                'is_next_day' => !empty($s['is_next_day']) || $outMin <= $inMin,
            ];
        }

        $numSessions = count($parsedSchedules);
        $sessionPunches = array_fill(0, $numSessions, []);

        if ($numSessions === 1) {
            $sessionPunches[0] = $uniquePunches;
        } elseif ($numSessions === 2) {
            $s0 = $parsedSchedules[0];
            $s1 = $parsedSchedules[1];
            $e0 = $s0['out_min']; // e.g. 12:00 = 720
            $s1In = $s1['in_min']; // e.g. 13:00 = 780

            $pre = [];
            $break = [];
            $post = [];

            foreach ($uniquePunches as $p) {
                $m = $p['minutes'];
                if ($m < $e0) {
                    $pre[] = $p;
                } elseif ($m > $s1In) {
                    $post[] = $p;
                } else {
                    $break[] = $p;
                }
            }

            // Collapse repeated scans of same type within break window (Scenario 02, 03, 13)
            $dedupedBreak = [];
            foreach ($break as $p) {
                if (empty($dedupedBreak)) {
                    $dedupedBreak[] = $p;
                    continue;
                }
                $last = end($dedupedBreak);
                $isSameType = ($last['type'] && $p['type'] && $last['type'] === $p['type']);
                $diffSec = $p['ts'] - $last['ts'];
                if ($isSameType && $diffSec <= 300) {
                    if ($p['type'] === 'O') {
                        // For OUT, retain the latest scan
                        array_pop($dedupedBreak);
                        $dedupedBreak[] = $p;
                    }
                    // For IN, retain the earliest scan (already in $dedupedBreak)
                } else {
                    $dedupedBreak[] = $p;
                }
            }
            $break = $dedupedBreak;

            if (count($break) === 0) {
                // A late morning checkout can occur after the afternoon start.
                // If it is followed by an afternoon IN, keep that OUT with the morning session.
                if (!empty($pre) && !empty($post) && $post[0]['type'] === 'O') {
                    $firstAfternoonIn = null;
                    foreach (array_slice($post, 1) as $postIndex => $postPunch) {
                        if ($postPunch['type'] === 'I') {
                            $firstAfternoonIn = $postIndex + 1;
                            break;
                        }
                    }
                    if ($firstAfternoonIn !== null) {
                        $sessionPunches[0] = array_merge($pre, [$post[0]]);
                        $sessionPunches[1] = array_slice($post, 1);
                    } else {
                        $sessionPunches[0] = $pre;
                        $sessionPunches[1] = $post;
                    }
                } else {
                    $sessionPunches[0] = $pre;
                    $sessionPunches[1] = $post;
                }
            } elseif (count($break) === 1) {
                $b = $break[0];
                if (empty($pre) && !empty($post)) {
                    // Scenario 12: Reversed afternoon sequence, no morning punches -> Afternoon
                    $sessionPunches[0] = [];
                    $sessionPunches[1] = array_merge([$b], $post);
                } elseif (!empty($pre) && empty($post)) {
                    // Scenario 11: Reversed morning sequence, no afternoon punches -> Morning
                    $sessionPunches[0] = array_merge($pre, [$b]);
                    $sessionPunches[1] = [];
                } elseif (!empty($pre) && !empty($post)) {
                    // Both morning and afternoon have punches
                    if ($b['type'] === 'I') {
                        // Scenario 06: Missing morning OUT, punch is marked I -> Afternoon IN
                        $sessionPunches[0] = $pre;
                        $sessionPunches[1] = array_merge([$b], $post);
                    } elseif ($b['type'] === 'O') {
                        // Scenario 07: Missing afternoon IN, punch is marked O -> Morning OUT
                        $sessionPunches[0] = array_merge($pre, [$b]);
                        $sessionPunches[1] = $post;
                    } else {
                        // Closer to e0 or s1In
                        if (abs($b['minutes'] - $e0) <= abs($b['minutes'] - $s1In)) {
                            $sessionPunches[0] = array_merge($pre, [$b]);
                            $sessionPunches[1] = $post;
                        } else {
                            $sessionPunches[0] = $pre;
                            $sessionPunches[1] = array_merge([$b], $post);
                        }
                    }
                } else {
                    // Pre and post both empty (single punch in the day, inside break)
                    if (abs($b['minutes'] - $e0) <= abs($b['minutes'] - $s1In)) {
                        $sessionPunches[0] = [$b];
                        $sessionPunches[1] = [];
                    } else {
                        $sessionPunches[0] = [];
                        $sessionPunches[1] = [$b];
                    }
                }
            } else {
                // count($break) >= 2
                // Look for transition from OUT to IN in break window (Scenario 13)
                $splitIdx = -1;
                for ($i = 0; $i < count($break) - 1; $i++) {
                    if ($break[$i]['type'] === 'O' && $break[$i + 1]['type'] === 'I') {
                        $splitIdx = $i;
                        break;
                    }
                }

                if ($splitIdx === -1) {
                    // If no explicit O->I transition, first punch is morning OUT, remaining are afternoon (Scenario 09, 10)
                    $splitIdx = 0;
                }

                $morningBreak = array_slice($break, 0, $splitIdx + 1);
                $afternoonBreak = array_slice($break, $splitIdx + 1);

                $sessionPunches[0] = array_merge($pre, $morningBreak);
                $sessionPunches[1] = array_merge($afternoonBreak, $post);
            }
        } else {
            // General N > 2 sessions
            foreach ($uniquePunches as $p) {
                $bestSession = 0;
                $bestDist = PHP_INT_MAX;
                foreach ($parsedSchedules as $idx => $s) {
                    $dist = min(abs($p['minutes'] - $s['in_min']), abs($p['minutes'] - $s['out_min']));
                    if ($p['minutes'] >= $s['in_min'] && $p['minutes'] <= $s['out_min']) {
                        $dist = 0;
                    }
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $bestSession = $idx;
                    }
                }
                $sessionPunches[$bestSession][] = $p;
            }
        }

        // For each session, assign IN and OUT
        $result = [];
        foreach ($parsedSchedules as $idx => $s) {
            $punchesInSession = $sessionPunches[$idx] ?? [];
            $count = count($punchesInSession);
            $in = null;
            $out = null;

            if ($count === 1) {
                $p = $punchesInSession[0];
                $distIn = min(abs($p['minutes'] - $s['in_min']), 1440 - abs($p['minutes'] - $s['in_min']));
                $distOut = min(abs($p['minutes'] - $s['out_min']), 1440 - abs($p['minutes'] - $s['out_min']));
                if ($distIn <= $distOut) {
                    $in = $p;
                } else {
                    $out = $p;
                }
            } elseif ($count >= 2) {
                $first = $punchesInSession[0];
                $last = $punchesInSession[$count - 1];
                $spanSeconds = $last['ts'] - $first['ts'];

                $calcDistIn = fn ($p) => min(abs($p['minutes'] - $s['in_min']), 1440 - abs($p['minutes'] - $s['in_min']));
                $calcDistOut = fn ($p) => min(abs($p['minutes'] - $s['out_min']), 1440 - abs($p['minutes'] - $s['out_min']));

                // A cluster occurs when repeated scans occur within 10 minutes (600s)
                $isCluster = ($spanSeconds <= 600);

                if ($isCluster) {
                    // All punches are clustered together (repeated/duplicate scans of one event)
                    // If cluster is closer to in_min -> arrival (IN), out is null
                    // If cluster is closer to out_min -> departure (OUT), in is null
                    $avgMinutes = array_sum(array_column($punchesInSession, 'minutes')) / $count;
                    $avgDistIn = min(abs($avgMinutes - $s['in_min']), 1440 - abs($avgMinutes - $s['in_min']));
                    $avgDistOut = min(abs($avgMinutes - $s['out_min']), 1440 - abs($avgMinutes - $s['out_min']));

                    if ($avgDistIn <= $avgDistOut) {
                        $in = $first;
                        $out = null;
                    } else {
                        $in = null;
                        $out = $last;
                    }
                } else {
                    // Punches span more than cluster window
                    // Separate punches into arrival side and departure side
                    $inCandidates = [];
                    $outCandidates = [];

                    foreach ($punchesInSession as $p) {
                        $dIn = $calcDistIn($p);
                        $dOut = $calcDistOut($p);
                        if ($dIn <= $dOut) {
                            $inCandidates[] = $p;
                        } else {
                            $outCandidates[] = $p;
                        }
                    }

                    // Check explicit punch types if all fell on one side of midpoint
                    $iPunches = array_values(array_filter($punchesInSession, fn ($p) => $p['type'] === 'I'));
                    $oPunches = array_values(array_filter($punchesInSession, fn ($p) => $p['type'] === 'O'));

                    if (!empty($iPunches) && !empty($oPunches) && empty($outCandidates)) {
                        // All before midpoint, but explicit I and O present (e.g. early undertime departure)
                        $in = $iPunches[0];
                        $out = end($oPunches);
                    } elseif (!empty($iPunches) && !empty($oPunches) && empty($inCandidates)) {
                        // All after midpoint, but explicit I and O present
                        $in = $iPunches[0];
                        $out = end($oPunches);
                    } else {
                        if (!empty($inCandidates)) {
                            $in = $inCandidates[0]; // Earliest arrival
                        }
                        if (!empty($outCandidates)) {
                            $out = end($outCandidates); // Latest departure
                        }
                    }
                }
            }

            $result[$idx] = [
                'in' => $in ? $in['time'] : null,
                'out' => $out ? $out['time'] : null,
                'in_punch' => $in ? $in['raw_punch'] : null,
                'out_punch' => $out ? $out['raw_punch'] : null,
            ];
        }

        return $result;
    }
}
