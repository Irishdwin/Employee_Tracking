import fs from 'node:fs/promises';
import {SpreadsheetFile} from '@oai/artifact-tool';
const source=process.argv[2] || 'Templates/SAMPLE.xlsx';
const prefix=process.argv[3] || 'template';
const book=await SpreadsheetFile.importXlsx(new Uint8Array(await fs.readFile(source)));
const sheet=book.worksheets.getItemAt(0);
console.log((await book.inspect({kind:'sheet',include:'id,name',maxChars:1500})).ndjson);
for(const [label,range] of [['employee','D3:O9'],['summary',process.argv[4] || 'B22:E45']]) {
 const blob=await book.render({sheetName:sheet.name,range,scale:1.5,format:'png'});
 await fs.writeFile('Testing/BillingFormulaExport/'+prefix+'-'+label+'.png',new Uint8Array(await blob.arrayBuffer()));
}
if(process.argv[4]) {
 const blob=await book.render({sheetName:'Payroll Reconciliation',range:'A1:J6',scale:1,format:'png'});
 await fs.writeFile('Testing/BillingFormulaExport/'+prefix+'-reconciliation.png',new Uint8Array(await blob.arrayBuffer()));
}
