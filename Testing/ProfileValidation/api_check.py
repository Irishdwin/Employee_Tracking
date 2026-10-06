"""Only the isolated fixture configured by Testing/PSGC/api_check.py; never production."""
import runpy
ctx=runpy.run_path('Testing/PSGC/api_check.py')
request,p,cookie=ctx['request'],ctx['p'],ctx['cookie']
bad=dict(p,postal_code='')
status,result=request('save_applicant_profile_setup.php',cookie,bad)
assert status==400 and set(result['errors'])=={'postal_code'},result
bad=dict(p,postal_code='',street_address='');bad['address_psgc']=dict(p['address_psgc'],barangay='')
status,result=request('save_applicant_profile_setup.php',cookie,bad)
assert status==400 and set(result['errors'])=={'postal_code','street_address','barangay'},result
bad=dict(p);bad['address_psgc']=dict(p['address_psgc'],region='1000000000')
status,result=request('save_applicant_profile_setup.php',cookie,bad)
assert status==400 and 'city' in result['errors'],result
print('PASS: actual HTTP 400 responses map Postal-only, all three missing fields, and invalid PSGC to specific field errors')
