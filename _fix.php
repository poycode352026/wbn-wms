<?php
use Illuminate\Support\Facades\DB;
DB::update("UPDATE users SET employee_id=? WHERE email=?", ["6260305001","superadmin@gmail.com"]);
DB::update("UPDATE users SET employee_id=? WHERE email=?", ["6260305002","dea@gmail.com"]);
$rows = DB::select("SELECT id,name,email,employee_id,role FROM users");
foreach($rows as $r) { echo $r->id." | ".$r->name." | ".$r->email." | ".($r->employee_id??"null")." | ".$r->role."\n"; }