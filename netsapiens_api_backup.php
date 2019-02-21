#!/usr/bin/php -q
<?PHP

$debug = ( (isset($argv[1]) ) ? $argv[1] : 0);

if ( $debug ) decho ("Starting\n");

require_once('./netsapiens_creds.php');

$file_date = date("Ymd");

$vars          = '';

$token = get_token();

$url = $base_url . "ns-api/";
$vars['object']     = "domain";
$vars['format']     = "json";
$vars['action']     = "read";

$domains = get_data($vars, $url);

if ( $debug >= 10 ) print_r($domains);
if ( $debug >= 10 ) exit;

$domain_count = count($domains);

decho ("Retrieved $domain_count domains\n");

# Sort the domains;
$i = 1000;
while ($i >= 0) {
 foreach ( $domains as $domain ) {
  if ( $domain->sub_count == $i ) $mydomains[] = $domain;
 }
 $i--;
}

unset($domains);
$domains = $mydomains;

$tables = array(
 "domain",
 "domain-billing",
 "subscriber",
 "device",
 "audio-moh",
 "audio-greeting",
 "callqueue",
 "callidemgr",
 "conference",
 "department",
 "dialplan",
 "dialpolicy",
 "dialrule",
 "phoneconfiguration",
 "phonenumber-did",
 "phonenumber",
 "smsnumber",
 "timeframe",
);
if ( $debug >= 10 ) print_r($tables);

$x=1;
foreach ( $domains as $id => $domain ) {
 if ( $debug >= 2 ) decho ("$x/$domain_count Working on $domain->domain with $domain->sub_count subscribers\n");
 $dom_path = $path . $domain->domain;
 if ( $debug >= 3 ) decho (" Creating path: $dom_path\n");
 if ( !is_dir($dom_path) ) {
  mkdir( $dom_path, 0700);
 }
 foreach ( $tables as $n => $table ) {
  if ( $debug >= 3 ) decho ("  Working on table $table\n");
  unset($vars);

  $vars['action']     = "read";
  $vars['format']     = "json";
  $vars['action']     = "read";
  $vars['domain']     = $domain->domain;
  $vars['object'] = $table;
  $file = "$dom_path/".$file_date."_".$table."s.json";

  switch ($table) {
   case "audio-greeting":
    $vars['object'] = "audio";
    $vars['type']   = "greeting";
    $file = "$dom_path/".$file_date."_".$table.".json";
    break;
   case "audio-moh":
    $vars['object'] = "audio";
    $vars['type']   = "moh";
    $file = "$dom_path/".$file_date."_".$table.".json";
    break;
   case "domain":
    $file = "$dom_path/".$file_date."_".$table.".json";
    break;
   case "domain-billing":
    $vars['object'] = "domain";
    $vars['billing'] = "yes";
    $file = "$dom_path/".$file_date."_".$table.".json";
    break;
   case "department":
    $vars['action']     = "list";
    break;
   case "phonenumber-did":
    $vars['object'] = "phonenumber";
    $vars['dialplan'] = "DID Table";
    unset($vars['domain']);
    $vars['dest_domain'] = $domain->domain;
    break;
   default:
  }

  $data = get_data($vars, $url);
  file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

  if ( $table == "callqueue" && isset($data[0] ) ) {
   foreach ( $data as $id => $ino ) {
    $vars['object'] = "agent";
    $vars['queue_name'] = $ino->queue_name;
    $agents = get_data($vars, $url);
    $file = "$dom_path/".$file_date."_".$table."s_agents_".$ino->queue_name.".json";
    file_put_contents($file, json_encode($agents, JSON_PRETTY_PRINT));
   }
  }

  if ( $domain->sub_count >= 50 && $table == "subscriber" ) {
   foreach ( $data as $sub => $sarray ) {
    if ( $sarray->srv_code == "system-aa"
#         || $sarray->vmail_provisioned == "yes"
       ) {
     $vars['object'] = "audio";
     $vars['domain'] = $domain->domain;
     $vars['user']   = $sarray->user;
     $vars['type']   = "greeting";
     $audio = $data = get_data($vars, $url);
     foreach ( $audio as $audiofname => $farray ) {
      $ruri = str_replace('&amp;','&', urldecode($farray->remotepath));
      if ( $debug >= 3 ) decho ("   Pulling aa-audio for $audiofname $ruri\n");
      $homepage = file_get_contents($ruri);
      $file = "$dom_path/".$file_date."_".$table."_".$sarray->user."_".$sarray->srv_code."_".$audiofname;
      if ( $debug >= 3 ) decho ("   Writing aa-file $file\n");
      file_put_contents($file, $homepage);
     }
    }
   }
  }

  if ( $domain->sub_count >= 50  && ( $table == "audio-moh" || $table == "audio-greeting" ) ) {
   foreach ( $data as $audiofname => $farray ) {
    $ruri = str_replace('&amp;','&', urldecode($farray->remotepath));
    if ( $debug >= 3 ) decho ("   Pulling audio for $audiofname $ruri\n");
    $homepage = file_get_contents($ruri);
    $file = "$dom_path/".$file_date."_".$table."_".$audiofname;
    if ( $debug >= 3 ) decho ("   Writing file $file\n");
    file_put_contents($file, $homepage);
   }
  }

 }

 $x++;

 if ( $debug >= 3 && $x >= 5 ) break;
}

if ( $debug ) decho ("Finished\n");


function get_data($vars, $url, $decode=1) {

 global $token;

 if ( $token->expires_at <= time() ) {
  decho (" Getting new token\n");
  $token = get_token();
 }

 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS,$vars);  //Post Fields
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

 $headers[] = "Authorization: Bearer $token->access_token";

 curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

 $server_output = curl_exec ($ch);
 $success = curl_getinfo($ch, CURLINFO_HTTP_CODE);

 if($server_output === false || $success != "200") {
  decho ("API returned a $success " . curl_error($ch) . " when trying to obtain data\n");
  exit;
  return curl_error($ch);
 }

 curl_close ($ch);

 if ( $decode ) {
  return json_decode($server_output);
 } else {
  return $server_output;
 }

}

function get_token() {

 global $base_url;
 global $token;

 $url = $base_url . "ns-api/oauth2/token/";

 global $client_id, $client_secret, $username, $password;

 $vars = Array('format' => 'json', 'grant_type' => 'password', 'client_id' => $client_id, 'client_secret' => $client_secret, 'username' => $username, 'password' => $password);

 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);  //Post Fields
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

 $returner = curl_exec($ch);

 $success = curl_getinfo($ch, CURLINFO_HTTP_CODE);

 if($returner === false || $success != "200") {
  decho ("API returned a $success " . curl_error($ch) . " when trying to obtain a token\n");
  exit;
 }

 curl_close ($ch);

 $token = json_decode($returner);

 $token->expires_at = time() + $token->expires_in - 60;
 $token->retrieve_time = time();

 return $token;

}

function decho($note) {

 echo date("Y-m-d H:i:s") . " " . $note;

}

?>
