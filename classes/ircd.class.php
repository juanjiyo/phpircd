<?php

class ircd {

var $forbidden = array("newConnecntion", "process", "welcome");

function newConnection($in, $key){
	global $core;
	$e = explode(" ", $in);
        $command = strtolower($e['0']);
	switch(@$command){
		case 'quit':
		$p = $e;
		unset($p['0']);
		if(count($p) > 1){
			$p = implode(" ", $p);
		} elseif(count($p) == 0){
			$p = "";
		} else {
			$p = $p['1'];
		}
		if($p[0] == ":"){
			$p = substr($p, 1);
		}
		$this->quit($key, $p);
		break;
		case 'pass':

		break;
		case 'user':
		//USER nick mode unused :Real Name
		if(count($e) < 5){
			$this->error('461', $key, 'USER');
			break;
		}
		$err = FALSE;
		while($err == FALSE){
		if(!preg_match("/^[a-zA-Z\[\]\\\|\^\`_\{\}]{1}[a-zA-Z0-9\[\]\\\|\^\`_\{\}]{0,16}$/", $e['1'])){
			$err = "{$e['1']}:Illegal characters.";
			continue;
		}
		//if(modes n shit){
			
		//}
		if(count($e) == 5){
			$rn = $e['4'];
			if($rn[0] == ":"){
                                $rn = substr($rn, 1);
                        }
		} else {
			for($i=4;$i < count($e);$i++){
				$rn[] = $e[$i];
			}
			$rn = implode(" ", $rn);
			if($rn[0] == ":"){
				$rn = substr($rn, 1);
			}
		}
		if(!preg_match("/^[a-zA-Z\[\]\\\|\^\`_\{\} ]{1}[a-zA-Z0-9\[\]\\\|\^\`_\{\} ]{0,19}$/", $rn)){
			$err = "$rn:Illegal characters.";
			continue;
		}
		break;
		}
		if($err){
			$this->error('432', $key,"$err");
		} else {
			unset($e['3']); //unused
			$core->_clients[$key]['username'] = $e['1'];
			$core->_clients[$key]['usermode'] = "";
			$core->_clients[$key]['realname'] = $rn;
			$core->_clients[$key]['channels'] = array();
			if($core->_clients[$key]['regbit'] ^ 1){
				$core->_clients[$key]['regbit'] += 1;
			}
			if($core->_clients[$key]['regbit'] == 3){
				$core->_clients[$key]['lastping'] = time();
				$core->_clients[$key]['lastpong'] = $core->_clients[$key]['lastping'];
                                $core->_clients[$key]['registered'] = TRUE;
				$core->_clients[$key]['prefix'] = $core->_clients[$key]['nick']."!".$core->_clients[$key]['username']."@".$core->_clients[$key]['address'];
				$this->welcome($key);
                        }
		}

		break;
		case 'nick':
		if(count($e) < 2){
                        $this->error('431', $key);
                        break;
                }
        	$nicks = array();
		foreach($core->_clients as $value){
			$nicks[] = $value['nick'];
		}
	        if(array_search($e['1'], $nicks) !== FALSE){
        	        $this->error('433', $key, $e['1']);
                	break;
	        }
		if(preg_match("/^[a-zA-Z\[\]\\\|\^\`\_\{\}]{1}[a-zA-Z0-9\[\]\\\|\^\`\_\{\}]{0,16}$/", $e['1'])){
			$core->_clients[$key]['nick'] = $e['1'];
			if($core->_clients[$key]['regbit'] ^ 2){
				$core->_clients[$key]['regbit'] +=2;
			}
			if($core->_clients[$key]['regbit'] == 3){
				$core->_clients[$key]['lastping'] = time();
				$core->_clients[$key]['lastpong'] = $core->_clients[$key]['lastping'];
				$core->_clients[$key]['registered'] = TRUE;
				$core->_clients[$key]['prefix'] = $core->_clients[$key]['nick']."!".$core->_clients[$key]['username']."@".$core->_clients[$key]['address'];
				$this->welcome($key);
			}
		} else {
			$this->error('432', $key, $e['1'].":Illegal characters.");
		}
		break;
		default:
		$this->error('451', $key, $e['0']);
	}

}

function process($in, $key){
	$e = explode(" ", $in);
	$command = strtolower($e['0']);
	unset($e['0']);
	$params = implode (" ", $e);
	if(method_exists(__CLASS__,$command) && array_search($command, $this->forbidden) === FALSE){
		$core->_clients[$key]['lastping'] = time();
		$this->$command($key, $params);
	} else {
		$this->error('421', $key, $command);
	}
}

function error($numeric, $key, $extra=""){
	global $core;
	$socket = $core->_client_sock[$key];
	$target = (empty($core->_clients[$key]['nick'])?"*":$core->_clients[$key]['nick']);
	$prefix = ":".$core->servname." ".$numeric." ".$target." ";
	switch($numeric){
	case 409:
	$message = $prefix.":No origin specified.";
	break;
	case 411:
	$message = $prefix.":No recipient given ($extra).";
	break;
	case 412:
	$message = $prefix.":No text to send.";
	break;
	case 421:
	$message = $prefix.$extra." :Unknown command.";
	break;
	case 422:
	$message = $prefix.":MOTD file missing.";
	break;
	case 431:
	$message = $prefix.":No nickname given.";
	break;
	case 432:
	$extra = explode(":",$extra);
	$message = $prefix."$extra[0] :Erroneous nickname".($extra['1']?": ".$extra['1']:"");
	break;
	case 433:
	$message = $prefix.$extra." :Nickname already in use.";
	break;
	case 451:
	$message = $prefix.$extra." :You have not registered.";
	break;
	case '461':
	$message = $prefix."$extra :Not enough parameters.";
	break;
	case '462':
	$message = $prefix.":You may not register more than once.";
	}
	$core->write($socket, $message);
}

function welcome($key){
	global $core;
	$socket = $core->_client_sock[$key];
	$cl = $core->_clients[$key];
	$core->write($socket, ":{$core->servname} 001 {$cl['nick']} :Welcome to the {$core->network} IRC network, {$cl['prefix']}");
	$core->write($socket, ":{$core->servname} 002 {$cl['nick']} :Your host is {$core->servname} running {$core->version}");
	$core->write($socket, ":{$core->servname} 003 {$cl['nick']} :This server was created {$core->createdate}");
	$core->write($socket, ":{$core->servname} 004 {$cl['nick']} {$core->servname} {$core->version} <umodes> <chanmodes>");
	$core->write($socket, ":{$core->servname} 005 {$cl['nick']} CHANTYPES={$core->config['ircd']['chantypes']} PREFIX=(qaohv)~&@%+ :are supported by this server");
	$this->motd($key);
}

function lusers($key, $p=""){
	
}

function motd($key, $p=""){
	global $core;
	$socket = $core->_client_sock[$key];
	$cl = $core->_clients[$key];
	if(empty($p)){
		if(file_exists("motd.txt")){
			$core->write($socket, ":{$core->servname} 375 {$cl['nick']} :- {$core->servname} Message of the day -");
			$motd = file("motd.txt");
			foreach($motd as $value){
				$core->write($socket, ":{$core->servname} 372 {$cl['nick']} :- ".rtrim($value));
			}
			$core->write($socket, ":{$core->servname} 376 {$cl['nick']} :End of MOTD");
		} else {
			$this->error('422', $key);
		}
	}
}

function nick($key, $p){
	global $core;
	$socket = $core->_client_sock[$key];
	if(empty($p)){
		$this->error('461', $key, 'NICK');
		return;
	}
	$nicks = array();
	foreach($core->_clients as $value){
		$nicks[] = $value['nick'];
	}
	if(array_search($p, $nicks) !== FALSE){
		$this->error('433', $key, $p);
		return;
	}
	if(preg_match("/^[a-zA-Z\[\]\\\|\^\`\_\{\}]{1}[a-zA-Z0-9\[\]\\\|\^\`\_\{\}]{0,16}$/", $p)){
		$core->write($socket, ":{$core->_clients[$key]['prefix']} NICK $p");
		$core->_clients[$key]['nick'] = $p;
		$core->_clients[$key]['prefix'] = $core->_clients[$key]['nick']."!".$core->_clients[$key]['username']."@".$core->_clients[$key]['address'];
		//foreach($core->_clients[$key]['channels'] as $key => $value){
		//	alert the channel's occupants
		//}
	} else {
		$this->error('432', $key, $p.":"."Illegal characters.");
	}
}

function ping($key, $p, $e=false){
	global $core;
	$socket = $core->_client_sock[$key];
	if($e){
		$core->write($socket, "PING :$p");
		return;
	}
	if(empty($p)){
		$this->error('461', $key, 'PING');
		return;
	}
	$p = explode(" ", $p);
	if(count($p) == 1){
		$p = $p['0'];
		if(strpos($p, ":") === 0){
                	$p = substr($p, 1);
        	}
		$core->write($socket, ":{$core->servname} PONG {$core->servname} ".":$p");
		$core->_clients[$key]['lastpong'] = time();
	} else {
		//ping some server
	}
}

function pong($key, $p){
	global $core;
        $socket = $core->_client_sock[$key];
	//PONG :samecrap
	if(strpos($p, ":") === 0){
		$p = substr($p, 1);
	}
	if($p == $core->servname){ //respond to keepalive ping
		if($core->_clients[$key]['lastpong'] < $core->_clients[$key]['lastping']){
			$core->_clients[$key]['lastpong'] = time();
		}
	}
}

function quit($key, $p){
	global $core;
	$socket = $core->_client_sock[$key];
	$core->write($socket, "ERROR: Closing Link: {$core->_clients[$key]['address']} ($p)");
	$core->close($key);
	//foreach($core->_clients[$key]['channels'] as $key => $value){
        //      alert the channel's occupants
        //}
}

function user($key, $p){
	$this->error('462', $key);
}
}

?>
