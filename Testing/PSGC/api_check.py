import json,subprocess,urllib.request,urllib.error
from pathlib import Path
base='http://127.0.0.1:8896/Api/Applicant/'
php=r'C:\xampp\php\php.exe'
def session(role='Applicant'):
 code='ini_set("session.save_path","C:/Users/Irishdwin Gamutan/AppData/Local/Temp/onazul-multisite-verified-zbtvnavh/sessions");session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=["user_id"=>51,"role"=>"'+role+'","last_activity"=>time()];echo session_id();'
 return subprocess.check_output([php,'-r',code],text=True).strip()
def request(path,cookie=None,payload=None):
 headers={'Content-Type':'application/json'}
 if cookie:headers['Cookie']='PHPSESSID='+cookie
 r=urllib.request.Request(base+path,data=json.dumps(payload).encode() if payload is not None else None,headers=headers)
 try:
  with urllib.request.urlopen(r,timeout=10) as response:return response.status,json.load(response)
 except urllib.error.HTTPError as e:return e.code,json.load(e)
cookie=session();assert request('get_psgc_locations.php')[0]==401;assert request('get_psgc_locations.php',session('Employee'))[0]==401
status,d=request('get_psgc_locations.php?level=regions',cookie);assert status==200 and len(d['data'])==18
status,d=request('get_psgc_locations.php?level=barangays&parent=1030500000',cookie);assert len(d['data'])==80;carmen=next(b for b in d['data'] if b['name']=='Carmen')
status,d=request('get_applicant_profile_setup.php',cookie);assert d['success'];p=d['profile'];p.update(first_name='Fixture',last_name='Address',middle_name='',has_suffix=0,birth_date='2000-01-01',street_address='123 Test Street',postal_code='9000',address_psgc={'region':'1000000000','province':'','city':'1030500000','barangay':carmen['id']},has_recent_experience=0,job_title='',company_name='')
status,d=request('save_applicant_profile_setup.php',cookie,p);assert status==200 and d['success'],(status,d)
status,d=request('get_applicant_profile_setup.php',cookie);assert d['profile']['address_psgc']==p['address_psgc'];assert d['profile']['state_province']=='';assert d['profile']['postal_code']=='9000';assert d['profile']['street_address']=='123 Test Street'
status,d=request('get_applicant_profile_setup.php',session());assert d['profile']['address_psgc']==p['address_psgc']
bad=dict(p);bad['address_psgc']=dict(p['address_psgc'],region='1300000000');assert request('save_applicant_profile_setup.php',cookie,bad)[0]==400
# Edit to NCR Manila and verify the province remains empty.
status,d=request('get_psgc_locations.php?level=barangays&parent=1380600000',cookie);assert len(d['data'])==897
p['address_psgc']={'region':'1300000000','province':'','city':'1380600000','barangay':d['data'][0]['id']};p['postal_code']='1000'
status,d=request('save_applicant_profile_setup.php',cookie,p);assert d['success'],d
status,d=request('get_applicant_profile_setup.php',cookie);assert d['profile']['address_psgc']==p['address_psgc'];assert d['profile']['state_province']==''
print('PASS: authenticated APIs, guest/other-role denial, CDO save/reload/new session, invalid POST rejection, edit to NCR save/reload')
