import json, subprocess, tempfile, shutil, urllib.request, urllib.parse, urllib.error, http.cookiejar, time, concurrent.futures, uuid, re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];PHP='C:/xampp/php/php.exe';MYSQL='C:/xampp/mysql/bin/mysql.exe';DB='onazul_auth_test_'+uuid.uuid4().hex[:16];checks=[]
def sql(q):return subprocess.run([MYSQL,'-h','127.0.0.1','-u','root','-N','-B','-e',q],capture_output=True,text=True,check=True).stdout.strip()
def check(n,ok):
 assert ok,n
 checks.append(n)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None,raw=False):
 body=json.dumps(data).encode() if raw else urllib.parse.urlencode(data).encode() if data is not None else None
 req=urllib.request.Request('http://127.0.0.1:18769/'+path,data=body,headers={'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Content-Type':'application/json' if raw else 'application/x-www-form-urlencoded'})
 try:r=c.open(req)
 except urllib.error.HTTPError as e:r=e
 text=r.read();
 try:return json.loads(text)
 except:raise AssertionError(text)
def login(c,email,pw,app=False,extra=None):
 d={'email':email,'password':pw} if app else {'username':email,'password':pw}
 if not app:
  cap=call(c,'Api/Login/captcha_gen.php',{});d.update(captcha_challenge_id=cap['challenge_id'],captcha_input=int(cap['numerator'])+int(cap['denominator']))
 d.update(extra or {});return call(c,'Api/Applicant/applicant_login.php' if app else 'Api/Login/login.php',d)
def fp(c,action,email='admin@example.test',**kw):return call(c,'Api/Login/forgot_password.php',dict(action=action,email=email,**kw))
with tempfile.TemporaryDirectory(prefix='onazul-auth-') as temp:
 t=Path(temp);files=['Common/session_init.php','Db_connection/db_connect.php','Login/login.php','Login/captcha_helper.php','Login/captcha_gen.php','Login/check_captcha.php','Login/forgot_password.php','Applicant/applicant_login.php','Helpers/auth_password_protection.php','Helpers/AuditLogger.php','Helpers/password_policy.php','Helpers/employee_password_history.php','Helpers/account_security.php','Helpers/google_auth_rbac.php','Helpers/google_verify_id_token.php','Employee/password.php','TeamLead/password.php']
 for f in files:
  p=t/'Api'/f;p.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(ROOT/'Api'/f,p)
 p=t/'Api/Db_connection/db_connect.php';p.write_text(p.read_text(encoding='utf-8').replace("'onazul_hris_db'",repr(DB)),encoding='utf-8')
 (t/'Api/Helpers/send_mail.php').write_text("<?php function send_mail_smtp($to,$subject,$body){file_put_contents(__DIR__.'/../../mail.json',json_encode([$to,$subject,$body]));return ['sent'=>true];}",encoding='utf-8')
 # CREATE (without IF NOT EXISTS) ensures we never adopt or drop a pre-existing database.
 sql('CREATE DATABASE '+DB);owned=True
 try:
  schema=subprocess.run(['C:/xampp/mysql/bin/mysqldump.exe','-h','127.0.0.1','-u','root','--no-data','onazul_hris_db','users','roles','employee_password_history','system_logs'],capture_output=True,text=True,check=True).stdout
  sql('USE '+DB+';SET FOREIGN_KEY_CHECKS=0;'+schema+'CREATE TABLE employee(Email VARCHAR(100),EmployeeNumber VARCHAR(100));CREATE TABLE applicants(UserID INT,ProfileDetailsCompleted INT,ProfileVisibilityCompleted INT,BirthDate DATE,ProfileStatus INT);CREATE TABLE notifications(UserID INT,NotificationType VARCHAR(100),IsRead INT);'+(ROOT/'sql/authentication_protection_migration.sql').read_text(encoding='utf-8'))
  roles=['Admin','HR','Accounting','TeamLead','Applicant','Employee'];hashed=subprocess.run([PHP,'-r',"echo password_hash('Violet!7824',PASSWORD_DEFAULT);"],capture_output=True,text=True,check=True).stdout
  for i,role in enumerate(roles,1):sql(f"USE {DB};INSERT INTO roles(RolesID,RoleName)VALUES({i},'{role}');INSERT INTO users(UsersID,RoleID,FullName,Email,Password,LocalPasswordConfigured)VALUES({i},{i},'Security fixture','{role.lower()}@example.test','{hashed}',1);")
  sql(f"USE {DB};INSERT INTO employee VALUES('employee@example.test','TEST-EMP');")
  server=subprocess.Popen([PHP,'-S','127.0.0.1:18769','-t',str(t)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL);time.sleep(1)
  def code():return re.search(r'code is: (\d{6})',json.loads((t/'mail.json').read_text())[2]).group(1)
  try:
   for role in roles:check(role+' login',login(client(),role.lower()+'@example.test','Violet!7824',role=='Applicant')['success'])
   c=client()
   for n in range(1,6):
    r=login(c,'admin@example.test','wrong',extra={'failed_attempts':0,'UserID':999,'lockout_status':False});check('Failure count '+str(n),not r['success'] and sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=1')==str(n))
   check('Fifth failure locks',r['code']=='login_locked' and r['retry_after']==300)
   check('Correct password in other session cannot bypass lock',login(client(),'admin@example.test','Violet!7824')['code']=='login_locked')
   check('Locked attempts do not alter count',sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=1')=='5')
   sql(f'UPDATE {DB}.users SET LoginLockedUntil=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE UsersID=1');check('Expired lock allows login',login(client(),'admin@example.test','Violet!7824')['success']);check('Success clears state',sql(f'SELECT CONCAT(FailedLoginAttempts,":",LoginLockedUntil IS NULL) FROM {DB}.users WHERE UsersID=1')=='0:1')
   login(client(),'employee@example.test','wrong');login(client(),'TEST-EMP','wrong');check('Employee number/email share counter',sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=6')=='2')
   login(client(),'applicant@example.test','wrong',True);login(client(),'applicant@example.test','wrong');check('Applicant endpoints share counter',sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=5')=='2')
   for i in range(5):unknown=login(client(),'nobody@example.test','wrong')
   check('Unknown login uses matching lockout response',unknown['code']=='login_locked')
   sql(f'UPDATE {DB}.users SET FailedLoginAttempts=5,LoginLockedUntil=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE UsersID=1')
   reset=client();sent=fp(reset,'send_code');otp=code();check('Unknown/known reset responses identical',sent==fp(client(),'send_code','unregistered@example.test'))
   check('Sending code does not unlock',sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=1')=='5')
   rows=sql(f'SELECT COUNT(*) FROM {DB}.password_reset_requests');fp(client(),'send_code');check('Resend rate limited across sessions',rows==sql(f'SELECT COUNT(*) FROM {DB}.password_reset_requests'))
   check('Other session cannot use code',not fp(client(),'verify_code',code=otp)['success']);check('Valid code verifies',fp(reset,'verify_code',code=otp)['success']);check('Verification does not unlock',sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=1')=='5')
   check('Weak password rejected',not fp(reset,'reset_password',new_password='password',confirm_password='password')['success'])
   check('Valid reset ignores injected account IDs',fp(reset,'reset_password',new_password='Azure!9317',confirm_password='Azure!9317',UserID=2,EmployeeID=999)['success'])
   check('Other account unchanged',login(client(),'hr@example.test','Violet!7824')['success']);check('Reset clears state',sql(f'SELECT CONCAT(FailedLoginAttempts,":",LoginLockedUntil IS NULL) FROM {DB}.users WHERE UsersID=1')=='0:1')
   check('Reset does not sign user in',not call(reset,'Api/Employee/password.php')['success']);check('Reset single use',not fp(reset,'reset_password',new_password='Tulip!3286',confirm_password='Tulip!3286')['success'])
   check('Old password rejected',not login(client(),'admin@example.test','Violet!7824')['success']);check('New password works',login(client(),'admin@example.test','Azure!9317')['success']);check('Password hashed',sql(f'SELECT Password FROM {DB}.users WHERE UsersID=1').startswith('$2y$'))
   sql(f'DELETE FROM {DB}.auth_rate_limits');expired=client();fp(expired,'send_code','hr@example.test');otp=code();fp(expired,'verify_code','hr@example.test',code=otp);sql(f'UPDATE {DB}.password_reset_requests SET ExpiresAt=UNIX_TIMESTAMP()-1 WHERE UserID=2');check('Expiry after verification rejected',not fp(expired,'reset_password','hr@example.test',new_password='Tulip!3286',confirm_password='Tulip!3286')['success'])
   sql(f'DELETE FROM {DB}.auth_rate_limits');guess=client();fp(guess,'send_code','accounting@example.test');otp=code()
   for i in range(5):fp(guess,'verify_code','accounting@example.test',code='100000' if otp!='100000' else '100001')
   check('Five OTP guesses block correct code',not fp(guess,'verify_code','accounting@example.test',code=otp)['success'])
   sql(f'DELETE FROM {DB}.auth_rate_limits');old=client();fp(old,'send_code','teamlead@example.test');otp=code();fp(old,'verify_code','teamlead@example.test',code=otp);sql(f'DELETE FROM {DB}.auth_rate_limits');fp(client(),'send_code','teamlead@example.test');check('New request invalidates verified old request',not fp(old,'reset_password','teamlead@example.test',new_password='Tulip!3286',confirm_password='Tulip!3286')['success'])
   # Existing Employee/Team Lead password change still uses shared policy/history and CSRF.
   for role in ['employee','teamlead']:
    user=client();login(user,role+'@example.test','Violet!7824');endpoint='Api/Employee/password.php' if role=='employee' else 'Api/TeamLead/password.php';state=call(user,endpoint);check(role+' settings available',state['success']);data=dict(action='change',csrf_token=state['csrf_token'],old_password='Violet!7824',new_password='Clover!3284',confirm_password='Clover!3284');check(role+' password change',call(user,endpoint,data,True)['success']);check(role+' new password login',login(client(),role+'@example.test','Clover!3284')['success'])
   # Legacy setup uses an authenticated account, existing CSRF/policy/history.
   sql(f'UPDATE {DB}.users SET LocalPasswordConfigured=0 WHERE UsersID=6')
   emp=client();login(emp,'employee@example.test','Clover!3284');sql(f'UPDATE {DB}.users SET LocalPasswordConfigured=0 WHERE UsersID=6')
   # GET setup notifications use an existing helper; inspect setup through POST with CSRF from prior settings.
   sql(f'UPDATE {DB}.users SET LocalPasswordConfigured=1 WHERE UsersID=6');state=call(emp,'Api/Employee/password.php');sql(f'UPDATE {DB}.users SET LocalPasswordConfigured=0 WHERE UsersID=6')
   check('Employee password setup',call(emp,'Api/Employee/password.php',dict(action='setup',csrf_token=state['csrf_token'],new_password='Peony!7294',confirm_password='Peony!7294'),True)['success'])
   check('Setup password login',login(client(),'employee@example.test','Peony!7294')['success'])
   emp=client();login(emp,'employee@example.test','Peony!7294');state=call(emp,'Api/Employee/password.php')
   check('Password history rejects reused password',not call(emp,'Api/Employee/password.php',dict(action='change',csrf_token=state['csrf_token'],old_password='Peony!7294',new_password='Violet!7824',confirm_password='Violet!7824'),True)['success'])
   check('Settings CSRF preserved',not call(emp,'Api/Employee/password.php',dict(action='change',csrf_token='wrong',old_password='Peony!7294',new_password='Lily!5739',confirm_password='Lily!5739'),True)['success'])
   check('Settings IDOR rejected',not call(emp,'Api/Employee/password.php',dict(action='change',csrf_token=state['csrf_token'],UserID=2,old_password='Peony!7294',new_password='Lily!5739',confirm_password='Lily!5739'),True)['success'])
   capClient=client();a=call(capClient,'Api/Login/captcha_gen.php',{});b=call(capClient,'Api/Login/captcha_gen.php',{})
   check('CAPTCHA challenges independent',a['challenge_id']!=b['challenge_id'] and call(capClient,'Api/Login/check_captcha.php',dict(challenge_id=a['challenge_id'],answer=int(a['numerator'])+int(a['denominator'])))['valid'])
   before=sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=2')
   check('Invalid CAPTCHA cannot authenticate',not call(capClient,'Api/Login/login.php',dict(username='hr@example.test',password='Violet!7824',captcha_challenge_id=b['challenge_id'],captcha_input='wrong'))['success'])
   check('Invalid CAPTCHA does not increase password failures',before==sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=2'))
   # Test the unchanged verified-Google RBAC bootstrap (not an external OAuth exchange).
   sql(f'ALTER TABLE {DB}.applicants ADD ApplicantID INT PRIMARY KEY AUTO_INCREMENT,ADD FullName VARCHAR(100),ADD Email VARCHAR(100),ADD MobileNumber VARCHAR(30),ADD ProfileCompleted INT DEFAULT 0')
   google=t/'google.php';google.write_text("<?php require __DIR__.'/Api/Common/session_init.php';require __DIR__.'/Api/Db_connection/db_connect.php';require __DIR__.'/Api/Helpers/google_auth_rbac.php';$roles=['admin','hr','accounting','teamlead','applicant','employee'];$out=[];foreach($roles as $role){$out[]=google_rbac_process_verified_email($conn,$role.'@example.test','Google fixture','http://localhost/Employee_Tracking/Api/Applicant/google_oauth_callback.php');}echo json_encode($out);",encoding='utf-8')
   run=subprocess.run([PHP,str(google)],capture_output=True,text=True);assert run.returncode==0,run.stderr+run.stdout;googleResults=json.loads(run.stdout)
   check('Verified Google bootstrap supports all six roles',len(googleResults)==6 and all(r['success'] for r in googleResults))
   check('Google leaves password lockout state untouched',before==sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=2'))
   sql(f'DELETE FROM {DB}.auth_rate_limits;UPDATE {DB}.users SET FailedLoginAttempts=0,LoginLockedUntil=NULL WHERE UsersID=2;')
   worker=t/'worker.php';worker.write_text("<?php require __DIR__.'/Api/Db_connection/db_connect.php';require __DIR__.'/Api/Helpers/auth_password_protection.php';$_SERVER['REMOTE_ADDR']='concurrent-test';echo json_encode(auth_password_login($conn,'hr@example.test','wrong'));",encoding='utf-8')
   def attack(_):
    r=subprocess.run([PHP,str(worker)],capture_output=True,text=True)
    assert r.returncode==0,r.stderr+r.stdout
    return json.loads(r.stdout)
   with concurrent.futures.ThreadPoolExecutor(max_workers=12) as pool:responses=list(pool.map(attack,range(12)))
   check('12 concurrent attempts stop at five',sql(f'SELECT FailedLoginAttempts FROM {DB}.users WHERE UsersID=2')=='5' and sum(r['code']=='invalid_credentials' for r in responses)==4)
   worker.write_text("<?php require __DIR__.'/Api/Db_connection/db_connect.php';require __DIR__.'/Api/Helpers/auth_password_protection.php';for($i=0;$i<61;$i++)$wait=auth_rate_limit($conn,'fixture-ip',60,300);echo $wait;",encoding='utf-8');check('IP rate limit enforced',subprocess.run([PHP,str(worker)],capture_output=True,text=True,check=True).stdout=='300')
  finally:server.terminate();server.wait()
 finally:
  if owned and re.fullmatch(r'onazul_auth_test_[0-9a-f]{16}',DB):sql('DROP DATABASE '+DB)
(ROOT/'Testing/AuthProtection/results.json').write_text(json.dumps(dict(passed=len(checks),checks=checks),indent=2),encoding='utf-8');print(json.dumps(dict(passed=len(checks),checks=checks),indent=2))
