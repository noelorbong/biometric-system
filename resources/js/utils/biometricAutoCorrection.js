/**
 * Auto-correct biometric punches according to the shift schedule.
 * Reference: biometric_attendance_reference_with_review_candidates.pdf
 * Output format: Morning IN | Morning OUT | Afternoon IN | Afternoon OUT
 */

export function autoCorrectPunches(punches = [], schedules = [], baseDate = null) {
  const dateStr = baseDate ? String(baseDate).slice(0, 10) : null

  // 1. Filter out invalid timestamps (Scenario 16) and filter by date if specified (Scenario 19)
  const validPunches = []
  for (const p of punches) {
    const rawTime = p?.time || p?.checktime || p?.CHECKTIME || null
    if (!rawTime) continue

    const d = new Date(rawTime)
    if (Number.isNaN(d.getTime())) continue

    const pad = (n) => String(n).padStart(2, '0')
    const itemDateStr = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
    if (dateStr !== null && itemDateStr !== dateStr) {
      continue
    }

    const rawType = String(p?.type || p?.checktype || p?.CHECKTYPE || '').toUpperCase()
    const minutes = (d.getHours() * 60) + d.getMinutes()

    validPunches.push({
      dateObj: d,
      time: `${itemDateStr} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`,
      ts: d.getTime(),
      minutes,
      type: rawType === 'I' || rawType === 'O' ? rawType : null,
      action: p?._override_action || p?.action || null,
      raw_punch: p,
    })
  }

  // Parse schedules
  const sortedSchedules = [...schedules].sort((a, b) => Number(a?.sequence || 0) - Number(b?.sequence || 0))
  const parsedSchedules = sortedSchedules.map((s, idx) => {
    const inParts = String(s?.time_in || '').split(':')
    const outParts = String(s?.time_out || '').split(':')
    const inMin = (Number(inParts[0] || 0) * 60) + Number(inParts[1] || 0)
    const outMin = (Number(outParts[0] || 0) * 60) + Number(outParts[1] || 0)
    return {
      index: idx,
      in_min: inMin,
      out_min: outMin,
      is_next_day: Boolean(s?.is_next_day) || outMin <= inMin,
    }
  })

  if (!validPunches.length || !parsedSchedules.length) {
    return parsedSchedules.map(() => ({
      in: null,
      out: null,
      in_action: null,
      out_action: null,
      in_punch: null,
      out_punch: null,
    }))
  }

  // 2. Sort chronologically (Scenario 18)
  validPunches.sort((a, b) => a.ts - b.ts)

  // 3. Deduplicate exact same-timestamp punches (Scenario 17)
  const uniquePunches = []
  for (const p of validPunches) {
    const last = uniquePunches[uniquePunches.length - 1]
    if (last && last.time === p.time) {
      continue
    }
    uniquePunches.push(p)
  }

  const numSessions = parsedSchedules.length
  const sessionPunches = Array.from({ length: numSessions }, () => [])

  if (numSessions === 1) {
    sessionPunches[0] = uniquePunches
  } else if (numSessions === 2) {
    const s0 = parsedSchedules[0]
    const s1 = parsedSchedules[1]
    const e0 = s0.out_min // e.g. 12:00 = 720
    const s1In = s1.in_min // e.g. 13:00 = 780

    const pre = []
    let breakPunches = []
    const post = []

    for (const p of uniquePunches) {
      const m = p.minutes
      if (m < e0) {
        pre.push(p)
      } else if (m > s1In) {
        post.push(p)
      } else {
        breakPunches.push(p)
      }
    }

    // Collapse repeated scans of same type within break window (Scenario 02, 03, 13)
    const dedupedBreak = []
    for (const p of breakPunches) {
      if (dedupedBreak.length === 0) {
        dedupedBreak.push(p)
        continue
      }
      const last = dedupedBreak[dedupedBreak.length - 1]
      const isSameType = Boolean(last.type && p.type && last.type === p.type)
      const diffSec = Math.round((p.ts - last.ts) / 1000)
      if (isSameType && diffSec <= 300) {
        if (p.type === 'O') {
          // For OUT, retain the latest scan
          dedupedBreak.pop()
          dedupedBreak.push(p)
        }
        // For IN, retain earliest scan (already in dedupedBreak)
      } else {
        dedupedBreak.push(p)
      }
    }
    breakPunches = dedupedBreak

    if (breakPunches.length === 0) {
      sessionPunches[0] = pre
      sessionPunches[1] = post
    } else if (breakPunches.length === 1) {
      const b = breakPunches[0]
      if (pre.length === 0 && post.length > 0) {
        // Scenario 12: Reversed afternoon sequence, no morning punches -> Afternoon
        sessionPunches[0] = []
        sessionPunches[1] = [b, ...post]
      } else if (pre.length > 0 && post.length === 0) {
        // Scenario 11: Reversed morning sequence, no afternoon punches -> Morning
        sessionPunches[0] = [...pre, b]
        sessionPunches[1] = []
      } else if (pre.length > 0 && post.length > 0) {
        // Both morning and afternoon have punches
        if (b.type === 'I') {
          // Scenario 06: Missing morning OUT, punch is marked I -> Afternoon IN
          sessionPunches[0] = pre
          sessionPunches[1] = [b, ...post]
        } else if (b.type === 'O') {
          // Scenario 07: Missing afternoon IN, punch is marked O -> Morning OUT
          sessionPunches[0] = [...pre, b]
          sessionPunches[1] = post
        } else {
          // Closer to e0 or s1In
          if (Math.abs(b.minutes - e0) <= Math.abs(b.minutes - s1In)) {
            sessionPunches[0] = [...pre, b]
            sessionPunches[1] = post
          } else {
            sessionPunches[0] = pre
            sessionPunches[1] = [b, ...post]
          }
        }
      } else {
        // Single punch in day, inside break
        if (Math.abs(b.minutes - e0) <= Math.abs(b.minutes - s1In)) {
          sessionPunches[0] = [b]
          sessionPunches[1] = []
        } else {
          sessionPunches[0] = []
          sessionPunches[1] = [b]
        }
      }
    } else {
      // breakPunches.length >= 2
      // Look for transition from OUT to IN in break window (Scenario 13)
      let splitIdx = -1
      for (let i = 0; i < breakPunches.length - 1; i += 1) {
        if (breakPunches[i].type === 'O' && breakPunches[i + 1].type === 'I') {
          splitIdx = i
          break
        }
      }

      if (splitIdx === -1) {
        // If no explicit O->I transition, first punch is morning OUT, remaining are afternoon (Scenario 09, 10)
        splitIdx = 0
      }

      const morningBreak = breakPunches.slice(0, splitIdx + 1)
      const afternoonBreak = breakPunches.slice(splitIdx + 1)

      sessionPunches[0] = [...pre, ...morningBreak]
      sessionPunches[1] = [...afternoonBreak, ...post]
    }
  } else {
    // General N > 2 sessions
    for (const p of uniquePunches) {
      let bestSession = 0
      let bestDist = Infinity
      for (let idx = 0; idx < parsedSchedules.length; idx += 1) {
        const s = parsedSchedules[idx]
        let dist = Math.min(Math.abs(p.minutes - s.in_min), Math.abs(p.minutes - s.out_min))
        if (p.minutes >= s.in_min && p.minutes <= s.out_min) {
          dist = 0
        }
        if (dist < bestDist) {
          bestDist = dist
          bestSession = idx
        }
      }
      sessionPunches[bestSession].push(p)
    }
  }

  // Assign IN and OUT for each session
  return parsedSchedules.map((s, idx) => {
    const punchesInSession = sessionPunches[idx] || []
    const count = punchesInSession.length
    let inPunch = null
    let outPunch = null

    const calcDistIn = (p) => {
      const d = Math.abs(p.minutes - s.in_min)
      return Math.min(d, 1440 - d)
    }
    const calcDistOut = (p) => {
      const d = Math.abs(p.minutes - s.out_min)
      return Math.min(d, 1440 - d)
    }

    if (count === 1) {
      const p = punchesInSession[0]
      const distIn = calcDistIn(p)
      const distOut = calcDistOut(p)
      if (distIn <= distOut) {
        inPunch = p
      } else {
        outPunch = p
      }
    } else if (count >= 2) {
      const first = punchesInSession[0]
      const last = punchesInSession[count - 1]
      const spanSeconds = Math.round((last.ts - first.ts) / 1000)

      // A cluster occurs when repeated scans occur within 10 minutes (600s)
      const isCluster = spanSeconds <= 600

      if (isCluster) {
        // All punches are clustered together (repeated/duplicate scans of one event)
        // If cluster is closer to in_min -> arrival (IN), out is null
        // If cluster is closer to out_min -> departure (OUT), in is null
        const avgMinutes = punchesInSession.reduce((sum, p) => sum + p.minutes, 0) / count
        const avgDistIn = Math.min(Math.abs(avgMinutes - s.in_min), 1440 - Math.abs(avgMinutes - s.in_min))
        const avgDistOut = Math.min(Math.abs(avgMinutes - s.out_min), 1440 - Math.abs(avgMinutes - s.out_min))

        if (avgDistIn <= avgDistOut) {
          inPunch = first
          outPunch = null
        } else {
          inPunch = null
          outPunch = last
        }
      } else {
        // Punches span more than cluster window
        // Separate punches into arrival side and departure side
        const inCandidates = []
        const outCandidates = []

        for (const p of punchesInSession) {
          const dIn = calcDistIn(p)
          const dOut = calcDistOut(p)
          if (dIn <= dOut) {
            inCandidates.push(p)
          } else {
            outCandidates.push(p)
          }
        }

        const iPunches = punchesInSession.filter((p) => p.type === 'I')
        const oPunches = punchesInSession.filter((p) => p.type === 'O')

        if (iPunches.length > 0 && oPunches.length > 0 && outCandidates.length === 0) {
          inPunch = iPunches[0]
          outPunch = oPunches[oPunches.length - 1]
        } else if (iPunches.length > 0 && oPunches.length > 0 && inCandidates.length === 0) {
          inPunch = iPunches[0]
          outPunch = oPunches[oPunches.length - 1]
        } else {
          if (inCandidates.length > 0) {
            inPunch = inCandidates[0] // Earliest arrival
          }
          if (outCandidates.length > 0) {
            outPunch = outCandidates[outCandidates.length - 1] // Latest departure
          }
        }
      }
    }

    return {
      in: inPunch ? inPunch.time : null,
      out: outPunch ? outPunch.time : null,
      in_action: inPunch ? inPunch.action : null,
      out_action: outPunch ? outPunch.action : null,
      in_punch: inPunch ? inPunch.raw_punch : null,
      out_punch: outPunch ? outPunch.raw_punch : null,
    }
  })
}
