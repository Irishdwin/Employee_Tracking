<?php
require __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
$row=['basic_total'=>1000,'gross_salary'=>1000,'daily_rate'=>500,'days_worked'=>2,'allowances'=>100,'gross_payroll'=>1100,'total_ee_deductions'=>100,'net_salary'=>1000,'sss_ee'=>50,'philhealth_ee'=>30,'pagibig_ee'=>20,'other_ee_deductions'=>0,'total_er_dues'=>50,'thirteenth_month_accrual'=>80,'admin_overhead_pct'=>10,'vat_pct'=>12,'admin_overhead_amount'=>123,'vat_on_overhead'=>14.76,'grand_total_invoice'=>1367.76];
$a=billing_apply_components($row,['days_worked'=>3,'daily_rate'=>600,'allowances'=>200,'sss_ee'=>60]);
if($a['basic_total']!==1800.0 || $a['gross_payroll']!==2000.0 || $a['net_salary']!==1890.0)throw new RuntimeException('Pay component reconciliation failed.');
try{billing_apply_components($row,['holiday_duty_hrs'=>8]);throw new RuntimeException('Missing holiday amount accepted.');}catch(InvalidArgumentException $e){}
$a=billing_apply_components($row,['holiday_duty_hrs'=>8,'holiday_duty'=>1000]);
if($a['gross_payroll']!==2100.0 || $a['net_salary']!==2000.0)throw new RuntimeException('New holiday component failed.');
try{billing_apply_components($row,['total_ee_deductions'=>5]);throw new RuntimeException('Invalid deduction total accepted.');}catch(InvalidArgumentException $e){}
echo "PASS: rates/days and statutory changes reconcile; newly added holiday hours require pay; inconsistent deduction totals rejected.\n";
