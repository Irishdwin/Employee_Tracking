const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
 const context=await browser.newContext();const a=await context.newPage(),b=await context.newPage();
 await a.goto('http://localhost/Employee_Tracking/index.html');
 await a.waitForFunction(()=>document.getElementById('captchaNumerator').value!=='');
 const original=await a.evaluate(()=>[+document.getElementById('captchaNumerator').value,+document.getElementById('captchaDenominator').value]);
 await b.goto('http://localhost/Employee_Tracking/index.html');await b.waitForFunction(()=>document.getElementById('captchaNumerator').value!=='');
 for(let i=0;i<5;i++){const pair=await b.evaluate(()=>[+document.getElementById('captchaNumerator').value,+document.getElementById('captchaDenominator').value]);if(pair[0]+pair[1]!==original[0]+original[1])break;await b.locator('#refreshCaptchaBtn').click();await b.waitForTimeout(300);}
 await a.locator('#captchaInput').fill(String(original[0]+original[1]));
 await a.waitForFunction(()=>document.getElementById('captchaMessage').textContent!=='');
 console.log(JSON.stringify({displayed:original,correctAnswer:original[0]+original[1],message:await a.locator('#captchaMessage').textContent()}));
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
