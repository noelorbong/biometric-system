import assert from 'node:assert/strict';
import { autoCorrectPunches } from '../resources/js/utils/biometricAutoCorrection.js';

const schedules = [
  { sequence: 1, time_in: '08:00', time_out: '12:00' },
  { sequence: 2, time_in: '13:00', time_out: '17:00' },
];
const punches = ['07:46:55', '12:08:25', '12:31:37', '15:00:49'].map(time => ({
  CHECKTIME: `2026-10-13 ${time}`, CHECKTYPE: 'O',
}));
const result = autoCorrectPunches(punches, schedules, '2026-10-13');
assert.deepEqual(result.flatMap(slot => [slot.in, slot.out]), punches.map(p => p.CHECKTIME));
assert.equal(result[1].out_punch, punches[3]);
const earlier = punches.map(p => ({ ...p }));
earlier[3].CHECKTIME = '2026-10-13 14:59:49';
assert.equal(autoCorrectPunches(earlier, schedules, '2026-10-13')[1].out, null);
assert.equal(autoCorrectPunches(punches, schedules, '2026-10-14')[1].out, null);
const added = ['07:30:00', '12:01:00', '12:13:00', '15:00:00'].map(time => ({
  CHECKTIME: `2026-11-02 ${time}`, CHECKTYPE: 'O', _override_action: 'add',
}));
const exact = autoCorrectPunches(added, schedules, '2026-11-02');
assert.deepEqual(exact.flatMap(slot => [slot.in, slot.out]), added.map(p => p.CHECKTIME));
assert.equal(exact[1].out_action, 'add');
assert.equal(autoCorrectPunches([added[3]], schedules, '2026-11-02')[1].out, null);
console.log('Frontend auto-correction midpoint checks passed.');
const lateSchedule = [
  { sequence: 1, time_in: '10:15', time_out: '12:00' },
  { sequence: 2, time_in: '12:15', time_out: '18:30' },
];
const latePunches = ['11:13:00', '12:02:00', '12:09:00', '17:12:00'].map(time => ({
  CHECKTIME: `2026-11-02 ${time}`, CHECKTYPE: 'O', _override_action: 'add',
}));
const late = autoCorrectPunches(latePunches, lateSchedule, '2026-11-02');
assert.deepEqual(late.flatMap(slot => [slot.in, slot.out]), latePunches.map(p => p.CHECKTIME));
assert.equal(late[0].in_action, 'add');
const cluster = ['11:55:00', '11:59:00'].map(time => ({ CHECKTIME: `2026-11-02 ${time}`, CHECKTYPE: 'O' }));
assert.equal(autoCorrectPunches(cluster, lateSchedule, '2026-11-02')[0].in, null);
assert.equal(autoCorrectPunches([latePunches[0]], lateSchedule, '2026-11-02')[0].in, null);
console.log('Frontend late-arrival and duplicate-cluster checks passed.');
