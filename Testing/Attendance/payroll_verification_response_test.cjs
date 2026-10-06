// Isolated response-contract tests; no browser session or database writes.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/attendance_testing.js', 'utf8');
const functionSource = source.slice(source.indexOf('  function fetchPayrollVerification('), source.indexOf('  function runVerification('));
const valid = { success: true, employee: {employee_id:1}, period: {start:'2026-09-11',end:'2026-09-25'}, payroll:{earnings:{},gross_pay:700,total_deductions:450,net_pay:250} };
async function run(status, body, reject = false) {
  const context = {state:{employeeId:1}, fetch: () => reject ? Promise.reject(new Error('offline')) : Promise.resolve({status,ok:status>=200&&status<300,text:()=>Promise.resolve(body)})};
  vm.createContext(context);
  vm.runInContext(functionSource, context);
  return context.fetchPayrollVerification('Api/get_payroll_verification.php?employee_id=1&date=2026-09-23');
}
(async () => {
  assert.equal((await run(200,JSON.stringify(valid))).payroll.net_pay,250);
  for (const [status, message] of [[404,'endpoint unavailable'],[401,'Sign in as Admin'],[403,'authorized Admin'],[400,'Invalid employee or payroll date'],[422,'salary or payroll cutoff'],[500,'server error']]) {
    await assert.rejects(run(status,'<html>private server error</html>'),new RegExp(message));
  }
  await assert.rejects(run(200,'not json'),/invalid server response/);
  await assert.rejects(run(200,JSON.stringify({...valid,employee:{employee_id:2}})),/mismatched data/);
  await assert.rejects(run(200,JSON.stringify({...valid,payroll:{earnings:{}}})),/incomplete/);
  await assert.rejects(run(0,'',true),/Network error/);
  console.log('PASS: valid response, six HTTP error categories, invalid JSON, mismatched employee, incomplete data, genuine network failure.');
})().catch(error=>{console.error(error);process.exitCode=1;});
