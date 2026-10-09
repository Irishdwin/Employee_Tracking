const fs=require('fs'),vm=require('vm'),assert=require('assert');
const src=fs.readFileSync(__dirname+'/attendance_testing.js','utf8');
const nodes={};function $(id){return nodes[id]||(nodes[id]={value:'',textContent:'',className:'',innerHTML:'',replaceChildren(){this.options=[];},add(o){this.options.push(o);}});}
$('inpDate').value='2026-10-09';$('selScenario').value='late';
let response={success:true,schedule:{time_in:'09:00:00',time_out:'18:00:00',grace_period:10},holidays:[{id:1,name:'Registered day',type:'Regular Holiday'},{id:2,name:'Second day',type:'Regular Holiday'}],holiday:{name:'Registered day',type:'Regular Holiday'},scheduled:'Scheduled Workday'};
const ctx={API:"Api/",$ ,state:{employeeId:1,cascadeVersion:1},esc:s=>s,Option:function(label,value){this.label=label;this.value=value;},updateOtAvailability(){},fetchJson:()=>Promise.resolve(response)};vm.createContext(ctx);
vm.runInContext(src.slice(src.indexOf('  function loadRegisteredHolidays()'),src.indexOf('  function currentAttendanceHours(')),ctx);
(async()=>{ctx.lookupHoliday();await new Promise(r=>setImmediate(r));assert.equal($('inpTimeIn').value,'09:25');assert.equal($('inpTimeOut').value,'18:00');assert($('holidayInfo').innerHTML.includes('Second day'));
response={success:true,holidays:[{date:'2026-12-25',name:'Admin Christmas',type:'Regular Holiday'}]};ctx.loadRegisteredHolidays();await new Promise(r=>setImmediate(r));assert.equal($('selRegisteredHoliday').options[0].value,'2026-12-25');assert($('selRegisteredHoliday').options[0].label.includes('Admin Christmas'));console.log('PASS: late uses actual shift/grace; double holidays displayed; registered holiday date/name loaded.');})().catch(e=>{console.error(e);process.exitCode=1});


