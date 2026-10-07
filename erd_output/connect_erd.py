from pathlib import Path
import xml.etree.ElementTree as E
import json,re,html

OUT=Path(__file__).resolve().parent
src=OUT/'Onazul_ERD_UPDATED.drawio'
tree=E.parse(src); root=tree.find('.//root'); cells=list(root)
audit=json.loads((OUT/'schema_audit.json').read_text(encoding='utf-8'))
schema=audit['schema']
def text(s): return html.unescape(re.sub('<[^>]+>','',s or '')).strip()
def norm(s): return re.sub('[^a-z0-9]','',text(s).lower())
entities={}
for c in cells:
 if 'shape=table;' in c.get('style',''):
  name=norm(c.get('value')); t='clearance_items' if name=='clearanceitem' else next(t for t in schema if norm(t)==name)
  entities[t]=c
rows={}
for t,c in entities.items():
 for row in cells:
  if row.get('parent')!=c.get('id'): continue
  cols=sorted([x for x in cells if x.get('parent')==row.get('id')],key=lambda x:float(x.find('mxGeometry').get('x','0')))
  if len(cols)==2: rows[t,text(cols[1].get('value'))]=row.get('id')

# Explicit evidence inventory. Dependencies carry no cardinality and are not FKs.
links=[]
def add(child,column,parent,key,kind,evidence,explanation):
 assert (child,column) in rows and (parent,key) in rows,(child,column,parent,key)
 links.append(dict(child=child,column=column,parent=parent,key=key,kind=kind,evidence=evidence,explanation=explanation))
R='application_reference'; D='dependency'; U='unverified_reference'
add('applicant_profiles','UserID','users','UsersID',R,'Api/Applicant/save_applicant_profile_setup.php:331','Profile upsert uses the authenticated session user ID.')
add('chat_threads','CreatedBy','users','UsersID',R,'Api/Helpers/chat_helper.php:129; Api/Common/messenger_thread.php:5','Thread creator supplied from the authenticated messenger actor.')
add('chat_messages','ThreadID','chat_threads','ThreadID',R,'Api/Common/messages_threads.php:36; Api/Helpers/chat_helper.php:194','Messages inserted and fetched for an existing thread.')
add('chat_messages','SenderUserID','users','UsersID',R,'Api/Common/messages_messages.php:51','Explicit INNER JOIN to users.UsersID.')
add('chat_thread_participants','ThreadID','chat_threads','ThreadID',R,'Api/Common/messages_threads.php:70','Explicit thread-to-participant join.')
add('chat_thread_participants','UserID','users','UsersID',R,'Api/Helpers/chat_helper.php:165; Api/Helpers/messenger_rbac.php:50','Participant IDs originate from authorized users.')
add('chat_thread_reads','ThreadID','chat_threads','ThreadID',R,'Api/Helpers/chat_helper.php:174; Api/Common/messages_threads.php:63','Read cursor maintained per thread.')
add('chat_thread_reads','UserID','users','UsersID',R,'Api/Helpers/chat_helper.php:208','Read cursor upsert uses viewer user ID.')
add('chat_thread_reads','LastReadMessageID','chat_messages','MessageID',R,'Api/Helpers/chat_helper.php:207; Api/Helpers/messenger_unread.php:12','Read cursor uses maximum MessageID; zero is an initial sentinel, not an enforced reference.')
add('daily_time_records','employee_id','employee','EmployeeID',R,'Api/TeamLead/get_tl_attendance_roster.php:82','Explicit attendance-to-employee join.')
add('payroll_runs','CompanyID','clientcompany','CompanyID',R,'Api/Helpers/client_billing_helper.php:464','Explicit payroll-run-to-client-company join.')
add('payroll_status_history','PayrollRunID','payroll_runs','PayrollRunID',R,'Api/Helpers/client_billing_helper.php:335; Api/Helpers/client_billing_helper.php:340','Status update and history insert share the same run ID.')
add('payroll_status_history','UpdatedBy','users','UsersID',R,'Api/Helpers/client_billing_helper.php:354','Explicit history-updater join to users.')
add('payroll_group_status_history','UpdatedBy','users','UsersID',R,'Api/Helpers/payroll_status_helper.php:84','Explicit history-updater join to users.')
add('payroll_group_status_history','CompanyID','clientcompany','CompanyID',R,'Api/Accounting/update_payroll_group_status.php:51; Api/Helpers/payroll_status_helper.php:108','Company ID validated against submitted DTR/payroll group before insert.')
add('payroll_group_status_history','SiteSupervisorID','users','UsersID',R,'Api/Helpers/payroll_status_helper.php:108; Api/Accounting/update_payroll_group_status.php:160','Team Lead ID validated against submitted DTR group; DTR supervisor has an enforced users FK.')
add('address_province_lookup','RegionID','address_region_lookup','RegionID',R,'Api/Applicant/get_provinces.php:12; sql/psgc_address_hierarchy_migration.sql:23','Region-filtered province lookup; migration documents the hierarchy but does not declare a constraint.')
add('psgc_locations','ParentPSGCCode','psgc_locations','PSGCCode',R,'Api/Helpers/psgc_address.php:38','Parent-child geographic traversal; no forced province for NCR/HUC cities.')
add('applicants','AddressPSGC','psgc_locations','PSGCCode',R,'Api/Applicant/save_applicant_profile_setup.php:105,324; Api/Helpers/psgc_address.php:50','JSON region/province/city/barangay codes validated against PSGC records. Multi-value application reference, not a scalar FK.')
add('system_logs','user_id','users','UsersID',R,'Api/Helpers/AuditLogger.php:14,27','Audit actor is the session user ID. Logger also falls back to applicant_id; that fallback is polymorphic and cannot be an enforced users FK.')
add('deployment','SiteID','deployment_sites','SiteID',R,'Api/TeamLead/get_tl_attendance_roster.php:78','Explicit deployment-site join.')
add('dtr_submissions','SiteID','deployment_sites','SiteID',R,'Api/TeamLead/submit_dtr.php:100,130','Authorized SiteID is validated and inserted into DTR submissions.')
add('attendance','EvidenceDeploymentID','deployment','DeploymentID',R,'Api/Helpers/add_back.php:24','Attendance evidence deployment ID compared against the actual deployment.')
add('clientcompany','Industry','industry_lookup','Name',D,'Js/Admin/admin_dashboard.js:559; Api/Admin/industries.php:61','Industry dropdown stores the selected name as text; not an IndustryID FK.')
add('job_order_requests','WorkSchedule','work_schedule_lookup','Name',D,'Html/TeamLead/request_job_order.html:408; Api/Common/work_schedules.php:62','Work schedule dropdown stores selected name as text and permits custom values; no FK.')
add('applicants','Region','address_region_lookup','Name',D,'Api/Applicant/get_regions.php:7; Js/Applicant/applicant_profile_cascades.js:10,61','Legacy applicant region options API is retained. Current selector uses psgc_locations instead; this is a legacy lookup association, not a current enforced or scalar-ID reference.')
add('payroll','SSSDeduction','sss_rates_config','monthly_salary_credit',D,'Api/Helpers/employee_payroll_helper.php:366,371','Payroll calculation selects an active SSS bracket by salary/MSC; computation dependency, not a stored key.')
add('payroll','PhilHealthDeduction','gov_percentage_rates','philhealth_ee_pct',D,'Api/Helpers/employee_payroll_helper.php:400,403','Payroll calculation reads the government rate configuration; computation dependency, not a stored key.')
add('payroll_run_status_history','PayrollRunID','payroll_runs','PayrollRunID',U,'Installed information_schema metadata; no current PHP/SQL implementation found','Candidate reference suggested by installed schema only. No FK constraint and no application usage found; explicitly unverified.')

old=[c for c in cells if c.get('edge')=='1']
existing={(c.get('source'),c.get('target')) for c in old}
for i,l in enumerate(links,1):
 child=rows[l['child'],l['column']]; parent=rows[l['parent'],l['key']]
 assert (parent,child) not in existing and (child,parent) not in existing
 existing.add((parent,child))
 color='#d97706' if l['kind']==U else '#777777' if l['kind']==D else '#000000'
 dash='1 4' if l['kind']==D else '6 4'
 style=f'edgeStyle=orthogonalEdgeStyle;fontSize=10;html=1;rounded=0;startArrow=none;endArrow=none;dashed=1;dashPattern={dash};strokeColor={color};labelBackgroundColor=#ffffff;'
 label='UNVERIFIED reference' if l['kind']==U else 'legacy address options' if l['parent']=='address_region_lookup' and l['kind']==D else 'lookup by name' if l['kind']==D and l['parent'] in ('industry_lookup','work_schedule_lookup') else 'rate calculation' if l['kind']==D else ''
 e=E.SubElement(root,'mxCell',id=f'onazul_app_link_{i}',parent='1',edge='1',source=parent,target=child,style=style,value=label,logicalKind=l['kind'],relationshipEvidence=l['evidence'],relationshipExplanation=l['explanation'],logicalRelationship=f"{l['child']}.{l['column']} -> {l['parent']}.{l['key']}")
 E.SubElement(e,'mxGeometry',relative='1',attrib={'as':'geometry'})

def geom(c):
 g=c.find('mxGeometry'); return [float(g.get(k,'0')) for k in ('x','y','width','height')]
x=min(geom(c)[0] for c in entities.values()); y=min(geom(c)[1] for c in entities.values())-190
legend=E.SubElement(root,'mxCell',id='onazul_connection_legend',parent='1',vertex='1',value='CONNECTION LEGEND\nSolid: declared MySQL foreign key (137)\nDashed black: verified application reference (23)\nDotted gray: lookup / calculation dependency (5, includes legacy)\nDashed orange: unverified legacy reference (1)\nSupplementary links do not declare FK constraints or cardinality.',style='whiteSpace=wrap;html=0;align=left;spacingLeft=10;fillColor=#ffffff;strokeColor=#000000;fontSize=12;',auditLegend='1')
E.SubElement(legend,'mxGeometry',x=str(x),y=str(y),width='600',height='160',attrib={'as':'geometry'})

# Check graph connectivity including labelled dependencies and the one candidate.
adj={t:set() for t in entities}
for t,c,p,k in audit['relationships']: adj[t].add(p); adj[p].add(t)
for l in links: adj[l['child']].add(l['parent']); adj[l['parent']].add(l['child'])
isolated=[t for t,v in adj.items() if not v]; assert not isolated,isolated
seen=set(); pending=['users']
while pending:
 t=pending.pop()
 if t in seen: continue
 seen.add(t); pending+=list(adj[t]-seen)
assert len(seen)==len(entities),(set(entities)-seen)
assert len(old)==137
assert all(dict(c.attrib)==dict(next(x for x in root if x.get('id')==c.get('id')).attrib) for c in old)
target=OUT/'Onazul_ERD_CONNECTED.drawio'; tree.write(target,encoding='utf-8',xml_declaration=True); E.parse(target)
counts={k:sum(l['kind']==k for l in links) for k in (R,D,U)}
(OUT/'application_relationships.json').write_text(json.dumps({'links':links,'counts':counts,'isolated_tables':isolated,'connected_component_tables':len(seen),'enforced_fk_count':137},indent=2),encoding='utf-8')
report=['# ERD connections update','','67 tables; 137 unchanged MySQL-enforced FK connectors; '+str(len(links))+' supplemental links.','All 67 tables have a visible connection and belong to one connected diagram component.','', 'Solid lines represent installed FK constraints. Black dashed lines represent references verified in code. Gray dotted lines represent name lookups or calculation dependencies. Orange dashed line is an explicitly unverified reference candidate. Supplemental links have no FK label or cardinality claim.','', 'The unverified payroll_run_status_history.PayrollRunID → payroll_runs.PayrollRunID connection is included to satisfy the requested complete visual connectivity, but it is NOT a verified relationship. No current application usage was found. It must not be treated as database enforcement.','', 'Original entity styles, positions, dimensions, columns and key labels are unchanged from Onazul_ERD_UPDATED.drawio. Database and production code untouched. Original and FK-only diagram preserved.','', '## Supplementary links and evidence']
for l in links:
 report+=['',f"- {l['child']}.{l['column']} → {l['parent']}.{l['key']} [{l['kind']}]",'  '+l['explanation'],'  Evidence: '+l['evidence']]
report+=['','## Validation',f'Counts: {counts}.','Missing declared FK connectors: 0. Duplicate endpoint pairs: 0. Isolated tables: 0. Connected component: 67 tables.','Editable XML parse: passed. Column and FK flags unchanged.','All FK-only validation claims remain limited to installed constraints. Supplementary links are separately classified.']
(OUT/'ERD_CONNECTIONS_REPORT.md').write_text('\n'.join(report),encoding='utf-8')
print(json.dumps({'tables':len(entities),'enforced_foreign_keys':137,'supplemental_links':len(links),'counts':counts,'isolated_tables':isolated},indent=2))
