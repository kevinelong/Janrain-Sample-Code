<?php
/**
 * Copyright 2011
 * Janrain Inc.
 * All rights reserved.
 */
ob_start();
$the_error = '';
if (file_exists('console.conf.php')) {
	require_once('console.conf.php');
} else {
	$the_error .= 'console.conf.php not found';
}
if (file_exists('../library/engage.lib.php')) {
	require_once('../library/engage.lib.php');
} elseif (file_exists('engage.lib.php')) {
	require_once('engage.lib.php');
} else {
	$the_error .= 'engage.lib.php not found';
}
if (file_exists('index.inc.php')) {
	require_once('index.inc.php');
} else {
	$the_error .= 'index.inc.php not found';
}


$site_domain = $_SERVER['SERVER_NAME'];
$base_request = str_ireplace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
$base_url = 'http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$base_request;
$current_url = 'http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
$token_url = $current_url;

$forty_stars = '****************************************';

if (ENGAGE_CONSOLE_API_KEY != ''){
	$api_key_default = $forty_stars;
} else {
	$api_key_default = '';
}

$action_map = array();
$action_map['auth_info'] = array(
	'app_dom' => array('required'=>true, 'default'=>ENGAGE_CONSOLE_APP_DOM),
	'token'		=> array('required'=>true, 'default'=>''),
	'api_key'	=> array('required'=>true, 'default'=>$api_key_default),/* Fill in your API key in the console.conf.php. */
	'format'	=> array('required'=>true, 'default'=>'json'),
	'extended'=> array('required'=>false, 'default'=>'')
);

$info_steps = array();
$info_steps['one'] = array (
	'title' => 'step_one_title',
	'info' => 'step_one_instructions',
	'trigger' => 'format'
	);
$info_steps['two'] = array (
	'title' => 'step_two_title',
	'info' => 'step_two_instructions',
	'trigger' => 'app_dom'
	);
$info_steps['three'] = array (
	'title' => 'step_three_title',
	'info' => 'step_three_instructions',
	'trigger' => 'token'
	);
$info_steps['four'] = array (
	'title' => 'step_four_title',
	'info' => 'step_four_instructions',
	'trigger' => 'api_key'
	);
$info_steps['five'] = array (
	'title' => 'step_five_title',
	'info' => 'step_five_instructions',
	'trigger' => 'identifier'
	);

$actions = array();
foreach ($action_map as $action=>$action_def) {
	$do = true;
	foreach ($action_def as $val_name=>$properties) {
		$val = $properties['default'];
		if (isset($_REQUEST[$val_name])) {
			$val = trim(strip_tags($_REQUEST[$val_name]));
		}
		if (!isset($actions[$action])) {
			$actions[$action] = array();
		}
		if ($properties['required'] === true && empty($val)){
			$val = '';
			$do = false;
		}
		$actions[$action][$val_name] = $val;
	}
	$actions[$action]['do'] = $do;
}

$clean = true;
reset($actions);
while (list($action, $vals) = each($actions)) {
	if ($vals['do'] === true) {
		$actions[$action]['do'] = false;
		switch ($action) {
			case 'auth_info':
				$extended = false;
				if ($vals['extended'] == 'true') {
					$extended = true;
				}
				if ( $vals['api_key'] == $forty_stars && ENGAGE_CONSOLE_API_KEY != '' ) {
					$vals['api_key'] = ENGAGE_CONSOLE_API_KEY;
				}
				$result = engage_auth_info($vals['api_key'], $vals['token'], $vals['format'], $extended);
				if ($result === false) {
					$clean = false;
				}else{
					$actions['parse_result'] = array(
						'result' => $result,
						'format' => $vals['format'],
						'export' => array('identifier' => array('profile','identifier')),
						'do' => true
					);
					if ($vals['format'] == 'json') {
						$actions['indent_json'] = array(
							'json' => $result,
							'do' => true
						);
					}
					if ($vals['format'] == 'xml') {
						$actions['indent_xml'] = array(
							'xml' => $result,
							'do' => true
						);
					}
				}
			break;
			case 'parse_result':
				$parse_result = engage_parse_result($vals['result'], $vals['format']);
				if ($parse_result === false) {
					$clean = false;
				} else {
					if (is_array($parse_result)) {
						if (isset($parse_result['err'])) {
							$engage_error = true;
						} elseif (is_array($vals['export'])) {
							foreach ($vals['export'] as $export_name => $export_path) {
								$export_val = '';
								foreach ($export_path as $e_key => $e_path) {
									if (empty($export_path)) {
										$export_val = $parse_result["$e_path"];
									} else {
										$export_val = $export_val["$e_path"];
									}
								}
								$$export_name = $export_val = '';
							}
						}
					} elseif (is_object($parse_result)) {
						if ($parse_result->err != '') {
							$engage_error = true;
						} elseif (is_array($vals['export'])) {
							foreach ($vals['export'] as $export_name => $export_path) {
								$export_val = '';
								foreach ($export_path as $e_key => $e_path) {
									if (empty($export_path)) {
										$export_val = $parse_result->$e_path;
									} else {
										$export_val = $export_val->$e_path;
									}
								}
								$$export_name = $export_val = '';
							}
						}
					}
					$actions['parse_dump'] = array(
						'data' => $parse_result,
						'do' => true
					);
				}
			break;
			case 'indent_json':
				$indent_json = indent_json($vals['json']);
				$actions['raw_dump'] = array(
					'data' => $indent_json,
					'do' => true
				);
			break;
			case 'indent_xml':
				$indent_xml = indent_xml($vals['xml']);
				$actions['raw_dump'] = array(
					'data' => $indent_xml,
					'do' => true
				);
			break;
			case 'raw_dump':
				$the_raw_result = $vals['data'];
			break;
			case 'parse_dump':
				$the_parse_result = print_r($vals['data'], true);
			break;
		}
	}
	if ($clean === false) {
		$the_errors = engage_get_errors();
		if ($the_errors === false) {
			$the_errors = array('unknown_error'=>'error');
		}
	}
}

$style = '';

if (!isset($the_raw_result)) {
	$the_raw_result = '';
}

if(!empty($the_raw_result)) {
	$style .= '		#raw_results_wrapper {
			display:block;
		}
';
}

if (!isset($the_parse_result)) {
	$the_result = '';
}

if(!empty($the_parse_result)) {
	$style .= '		#parse_results_wrapper {
			display:block;
		}
';
}

if (!isset($the_error)) {
	$the_error = '';
}

if (isset($the_errors)) {
	foreach ($the_errors as $error_msg=>$label) {
		$the_error .= "$error_msg" . "\n";
	}
}

if (!empty($the_error)) {
	$the_error = str_replace(ENGAGE_CONSOLE_API_KEY, $fourty_stars, $the_error);
	$style .= '		#the_errors {
			display:block;
		}
';
}


$step_style = '';
$step_missed = false;
foreach ($info_steps as $info_step=>$info_vals) {
	if ( !empty($actions['auth_info'][$info_vals['trigger']]) && $step_missed === false){
		$step_style = 
'		#instructions #'.$info_vals['info'].' {
			width:780px;
			height:75px;
			border:0px;
			top:26px;
			left:0px;
			background-color:#FFF;
			font-size:13px;
			display:block;
		}
		#instructions #'.$info_vals['title'].' {
			color:#000;
			background-color:#FFF;
		}
';
	} else {
		$step_missed = true;
	}
}

if ($engage_error === true || !empty($the_error)){
	$step_style = 
'		#instructions #step_error_instructions {
			width:780px;
			height:75px;
			border:0px;
			top:26px;
			left:0px;
			background-color:#FEE;
			font-size:13px;
			display:block;			
		}
		#instructions #step_error_title {
			display:block;
			color:#000;
			background-color:#FEE;
		}
';
}

$style .= $step_style;

ob_end_clean();
ob_start();
?>
<!DOCTYPE html>
<html>
	<head>
	<meta charset="UTF-8" />
	<title>Janrain Engage API console</title>
	<link rel="stylesheet" type="text/css" media="screen" href="style.css" />
	<style type="text/css">
<?php echo $style; ?>
	</style>
</head>
<body>
	<div id="toolkit_wrapper">
	<div id="main_toolkit">
		<div id="page_title">
			Janrain Engage API console
		</div>
		<div id="top_ui">
			<div id="api_menu">
				<ul id="api_menu_list">
					<li id="menu_auth_info">auth_info</ol>
					</li>
				</ul>
			</div>
			<div id="instructions_wrapper">
				<div id="instructions">
					<div id="step_one" class="instruction_step">
					<h3 id="step_one_title" class="instruction_title">Step One&nbsp;-&gt;</h3>
					<div id="step_one_instructions" class="instruction">
						Copy and paste your Application Domain from your <a target="_blank" href="https://rpxnow.com/">Engage dashboard.</a><br />
						The domain should be a full URL. (e.g. https://myapp.rpxnow.com/) Click submit after pasting the URL.<br />
						<form id="app_domain_form" method="get">
							<label for="app_domain_input">Application Domain</label>
							<input id="app_domain_input" type="text" name="app_dom" value="<?php echo htmlentities($actions['auth_info']['app_dom']); ?>" />
							<input id="app_domain_submit" type="submit"	name="phpapi" value="submit" />
						</form>
					</div>						
					</div>
					<div id="step_two" class="instruction_step">
					<h3 id="step_two_title" class="instruction_title">Step Two&nbsp;-&gt;</h3>
					<div id="step_two_instructions" class="instruction">
						<div id="whitelist_note" class="note">
							You may need to whitelist your current domain. <br />
							<a target="_blank" href="https://rpxnow.com/">Engage dashboard</a>-&gt;Settings-&gt;<br />
							&nbsp;add&nbsp;"<?php echo htmlentities($site_domain); ?>" to "Token URL Domains"
						</div>
						Click <a class="rpxnow" onclick="return false;"					
						href="<?php echo htmlentities($actions['auth_info']['app_dom']); ?>openid/v2/signin?token_url=<?php echo urlencode($token_url); ?>"> Sign In </a><br />
						This will fill in the token.<br />
						The token is one time use by default.
					</div>
					</div>
					<div id="step_three" class="instruction_step">
					<h3 id="step_three_title" class="instruction_title">Step Three&nbsp;-&gt;</h3>
					<div id="step_three_instructions" class="instruction">
						Copy your API key from your <a target="_blank" href="https://rpxnow.com/">Engage dashboard.</a><br />
						Paste the API key in to the apiKey field.<br />
						Select format and extended(Plus/Pro/Enterprise only) options.
						Click submit.
					</div>
					</div>
					<div id="step_four" class="instruction_step">
					<h3 id="step_four_title" class="instruction_title">Step Four&nbsp;&nbsp;&nbsp;</h3>
					<div id="step_four_instructions" class="instruction">
						You have completed the auth_info API call.<br />
						This is the point where your code would begin.<br />
						<a href="<?php echo htmlentities($current_url); ?>">Start over</a>
					</div>
					</div>
					<div id="step_error" class="instruction_step">
					<h3 id="step_error_title" class="instruction_title">Oops!&nbsp;&nbsp;&nbsp;</h3>
					<div id="step_error_instructions" class="instruction">
						Make sure your token URL domain is in your token URL domain list.<br />
						Remember that tokens are one-time use.<br />
						Reusing a token can result in "Data not found".<br />
						<a href="<?php echo htmlentities($base_url); ?>">Start over</a>.
					</div>
					</div>
				</div>
			</div>
		</div>
		<div class="instruction">
		<div id="step_five" class="instruction_step">
		<h3 id="step_five_title" class="instruction_title">Indentifier Collected</h3>
		<div id="step_five_instructions" class="instruction">
		Hi <?php echo $identifier; ?>
		</div>
		</div>
		</div>
		<div id="main_form_wrapper">
			<form id="main_form" method="post">
				<label for="token">token</label>
				<input id="token" type="text" name="token" value="<?php echo htmlentities($actions['auth_info']['token']); ?>" />
				<label for="api_key">apiKey</label>
				<input id="api_key" type="text" name="api_key" value="<?php echo htmlentities($actions['auth_info']['api_key']); ?>" />
				<label for="format">format</label>
				<select id="format" name="format">
<?php
	$select_json = '';
	$select_xml = '';
	$selected = ' selected="selected"';
	if ($actions['auth_info']['format'] == 'json') {
		$select_json = $selected;
	} elseif ($actions['auth_info']['format'] == 'xml') {
		$select_xml = $selected;
	}
?>
					<option value="json"<?php echo $select_json; ?>>json</option>
					<option value="xml"<?php echo $select_xml; ?>>xml</option>
				</select>
				<label for="extended">extended</label>
				<input id="extended" type="checkbox" name="extended" value="true"<?php 
				if ($actions['auth_info']['extended'] == 'true') { echo ' checked="checked"'; } ?> />
				<input type="submit" name="go" value="submit" />
				<div id="api_sections">
					<div id="auth_info_section">
						<div id="auth_info_instructions">
						</div>
						<div id="auth_info_options">
						</div>
					</div>
				</div>
			</form>
		</div>
		<div id="output_wrapper">
			<div id="results_wrapper">
				<form id="results_form">
					<div id="raw_results_wrapper">
						<div id="raw_results_title">The response:</div>
						<div id="the_raw_results" class="results"><pre>
<?php echo htmlentities($the_raw_result); ?>

						</pre></div>
					</div>
					<div id="parse_results_wrapper">
						<div id="parse_results_title">The parsed (as an array) response:</div>					
						<div id="the_parse_results" class="results"><pre>
<?php echo htmlentities($the_parse_result); ?>

						</pre></div>
					</div>
				</form>
			</div>
			<div id="error_wrapper">
				<form id="error_form">
					<div id="the_errors" class="results"><pre>
<?php echo htmlentities($the_error); ?>

					</pre></div>
				</form>
			</div>
		</div>
	</div>
	</div>
	<script type="text/javascript">
		var rpxJsHost = (("https:" == document.location.protocol) ? "https://" : "http://static.");
		document.write(unescape("%3Cscript src='" + rpxJsHost +
			"rpxnow.com/js/lib/rpx.js' type='text/javascript'%3E%3C/script%3E"));
	</script>
	<script type="text/javascript">
		RPXNOW.overlay = true;
		RPXNOW.language_preference = 'en';
	</script>
</body>
</html><?php
ob_end_flush();
?>
