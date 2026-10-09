<?php
declare(strict_types=1);
function resume_document_type(string $type): bool { return (bool)preg_match('/^(resume|cv|resume\s*\/\s*cv|curriculum vitae)$/i',trim($type)); }
function resume_validate_file(array $file): void {
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_file($file['tmp_name']??''))throw new InvalidArgumentException('Please upload your Resume/CV in PDF format.');
 $size=filesize($file['tmp_name']);
 if($size===false || $size<=0 || $size>5*1024*1024)throw new InvalidArgumentException('Resume/CV must not exceed 5 MB.');
 if(strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION))!=='pdf' || (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])!=='application/pdf')throw new InvalidArgumentException('Only PDF files are allowed for Resume/CV.');
 $data=file_get_contents($file['tmp_name']);
 if(!preg_match('/^%PDF-(1\.[0-9]|2\.0)/',$data) || !preg_match('/%%EOF\s*$/',$data) || !preg_match('/startxref\s+\d+\s+%%EOF\s*$/',$data) || (!preg_match('#/Type\s*/Catalog\b#',$data) && !preg_match('#/Type\s*/ObjStm\b#',$data)))throw new InvalidArgumentException('The Resume/CV PDF is invalid or corrupted. Please upload a valid PDF.');
 if(preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|RichMedia)\b#i',$data))throw new InvalidArgumentException('Active content is not allowed in Resume/CV PDFs.');
}
