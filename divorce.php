<?php
$global_env = [
	[
		"->" => "_lambda",
		"=" => "_define",
		"'" => "_quote",
		"?" => "_if",
		"load" => "_load",
		"begin" => "begin",
		"+" => "plus",
		"-" => "subtract",
		"*" => "multiply",
		"div" => "divide",
		"==" => "equal",
		"<" => "less_than",
		"@" => "nth",
	],
	null
];
eval_file("library.divo", $global_env);
if(count($argv) > 1) {
	$res;
	for($i = 1; $i < count($argv); $i++) {
		$res = eval_file($argv[$i], $global_env);
	}
	_print($res);
	_print("=> Loaded.");
}
repl("> ", $global_env);

function eval_file($path, &$env) {
	if(file_exists($path))
		return _eval(read_file($path), $env);
}
function read_file($path) {
	$str = file_get_contents($path);
	return parse($str);
}
function read_line($prompt) {
	$line = readline($prompt);
	readline_add_history($line);
	return $line;
}
function _read($prompt) {
	return parse(read_line($prompt));
}
function parse($str) {
	$tokens = tokenize($str);
	return read_from_tokens($tokens);
}
function tokenize(&$str) {
	$tokens = [];
	for(;;) {
		skip_spaces($str);
		if(!$str)
			break;
		$c = c($str);
		if(is_special($c))
			$tokens[] = $c;
		elseif(is_quote($c)) {
			$tokens[] = $c;
			$tokens[] = take_string($str);
			$tokens[] = $c;
		} else {
			$str = $c . $str;
			$tokens[] = take_atom($str);
		}
	}
	return $tokens;
}
function c(&$str) {
	$c = $str[0];
	$str = substr($str, 1);
	return $c;
}
function skip_spaces(&$str) {
	while($str and is_space($str[0]))
		c($str);
}
function is_space($c) {
	return in_array($c, [" ", "\n", "\t", "\r"]);
}
function is_special($c) {
	return in_array($c, ["(", ")", "{", "}", "[", "]"]);
}
function is_quote($c) {
	return $c == "\"";
}
function take_string(&$str) {
	$token = "";
	while($str and $str[0] != "\"")
		$token .= c($str);
	if($str)
		c($str);
	return $token;
}
function not_special($c) {
	return !(is_special($c) or is_space($c) or is_quote($c));
}
function take_atom(&$str) {
	$token = "";
	while($str and not_special($str[0]))
		$token .= c($str);
	return $token;
}
function read_list(&$tokens, $end) {
	$L = [array_shift($tokens)];
	while($tokens and $tokens[0] != $end)
		$L[] = read_from_tokens($tokens);
	array_shift($tokens);
	return $L;
}
function read_from_tokens(&$tokens) {
	if(!$tokens)
		return;
	$lists = [
		["(", ")"],
		["{", "}"],
		["[", "]"],
		["\"", "\""],
	];
	foreach($lists as $list) {
		$begin = $list[0];
		if($tokens[0] == $begin) {
			$end = $list[1];
			return read_list($tokens, $end);
		}
	}
	return atom(array_shift($tokens));
}
function atom($x) {
	if(is_numeric($x)) {
		if(filter_var($x, FILTER_VALIDATE_INT) !== false)
			return intval($x);
		return floatval($x);
	}
	return $x;
}
function _eval($exp, &$env) {
	if(is_divorce_atom($exp))
		return fetch($exp, $env);
	if(is_divorce_string($exp))
		return $exp;
	return apply($exp, $env);
}
function is_divorce_atom($x) {
	return !is_array($x);
}
function is_divorce_string($x) {
	return is_array($x) and isset($x[0]) and $x[0] == "\"";
}
function divorce_string($x) {
	return $x[1];
}
function is_prefix($c) {
	return $c == "(";
}
function is_postfix($c) {
	return $c == "{";
}
function is_infix($c) {
	return $c == "[";
}
function fetch($exp, $env) {
	while(true) {
		if(isset($env[0][$exp]))
			return $env[0][$exp];
		if($env[1])
			$env = $env[1];
		else
			return $exp;
	}
}
function apply($exp, &$env) {
	$pos = array_shift($exp);
	if(is_prefix($pos))
		$fun = array_shift($exp);
	elseif(is_postfix($pos))
		$fun = array_pop($exp);
	elseif(is_infix($pos)) {
		$fun = $exp[1];
		$exp = array_merge([$exp[0]], array_slice($exp, 2));
	}
	$fun = _eval($fun, $env);
	if(is_callable($fun)) {
		if(is_special_form($fun))
			return $fun($exp, $env);
		$exp = evlist($exp, $env);
		return $fun($exp);
	}
	$exp = evlist($exp, $env);
	$params = $fun[0];
	$body = $fun[1];
	$exec_env = $fun[2];
	$exec_env = bind($params, $exp, $exec_env);
	return _eval($body, $exec_env);
}
function evlist($exp, &$env) {
	foreach($exp as $i => $arg) {
		$exp[$i] = _eval($arg, $env);
	}
	return $exp;
}
function bind($params, $args, &$env) {
	if(!is_array($params)) {
		$env[0][$params] = $args;
	} elseif(($idx = array_search(".", $params)) !== false) {
		bind(array_slice($params, 0, $idx),
		     array_slice($args, 0, $idx),
		     $env);
		bind($params[$idx + 1],
		     array_slice($args, $idx + 1),
		     $env);
	} else {
		foreach($params as $i => $param) {
			$arg = $args[$i];
			$env[0][$param] = $arg;
		}
	}
	return $env;
}
function is_special_form($name) {
	return $name[0] == "_";
}
function _print($exp) {
	if(is_divorce_atom($exp)) {
		echo "$exp\n";
	} else {
		if(is_divorce_string($exp))
			_print("\"" . divorce_string($exp) . "\"");
		else
			print_r($exp);
	}
}
function repl($prompt, &$env) {
	for(;;) {
		$str = read_line($prompt);
		_print(_eval(parse($str), $env));
	}
}

/* Special forms */
function _lambda($exp, &$env) {
	if(is_array($exp[0]))
		array_shift($exp[0]);
	return array_merge($exp, [[[], &$env]]);
}
function _quote($exp, &$env) {
	return $exp[0];
}
function _if($exp, &$env) {
	return _eval($exp[_eval($exp[0], $env) ? 1 : 2], $env);
}
function _define($exp, &$env) {
	if(is_array($exp[0])) {
		$pos = array_shift($exp[0]);
		if(is_prefix($pos)) {
			$f = array_shift($exp[0]);
		} elseif(is_postfix($pos)) {
			$f = array_pop($exp[0]);
		} elseif(is_infix($pos)) {
			$f = $exp[0][1];
			$exp[0] = array_merge([$exp[0][0]],
					      array_slice($exp[0], 2));
		}
		array_unshift($exp[0], $pos);
		return $env[0][$f] = _lambda($exp, $env);
	}
	return $env[0][$exp[0]] = _eval($exp[1], $env);
}
function _load($exp, &$env) {
	foreach($exp as $i => $x) {
		$exp[$i] = eval_file(divorce_string(
			_eval($x, $env)), $env);
	}
	return $exp[count($exp) - 1];
}

/* Functions */
function begin($list) {
	return $list[count($list) - 1];
}
function plus($list) {
	return $list[0] + $list[1];
}
function subtract($list) {
	if(count($list) == 1)
		return -$list[0];
	return $list[0] - $list[1];
}
function multiply($list) {
	return $list[0] * $list[1];
}
function divide($list) {
	return $list[0] / $list[1];
}
function equal($list) {
	return $list[0] === $list[1];
}
function less_than($list) {
	return $list[0] < $list[1];
}
function nth($list) {
	$arr = $list[0];
	$idx = $list[1];
	if(is_divorce_string($arr)) {
		$str = divorce_string($arr);
		return isset($str[$idx]) ? $str[$idx] : false;
	}
	return isset($arr[$idx]) ? $arr[$idx] : false;
}
?>
