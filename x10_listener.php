#!/usr/bin/php
<?php

// Listener to emulate the TIP10RF X10 remote. This daemon will listen and send HEYU commands
//  (C)2015 Andy Brown https://github.com/andyb2000

// SETTINGS CONFIGURED HERE:

$server_address="192.168.1.1";
$listen_port="5001";
$heyu_path="/usr/local/bin/heyu";

// 00---------------------------------------------------------------------------------------00

// A bit of information
//   000000000000000000000000FEA8
//	appear to be HELO messages from the client (unsure if we need to reply to these?)
//	We do need to reply with a carriage return afterwards as the client seems happy then
//   0000000000000000000060000036
//	A1 on (non dimmable)
//   0000000000000000000060200016
//	A1 off
//   0000000000000000000060100026
//	A2 on
//   0000000000000000000060300006
//	A2 off

// ------------------------------------------------------------------------------------------

error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
};

if (@socket_bind($sock, $server_address, $listen_port) === false) {
    echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
	echo "CANNOT bind to $server_address $listen_port did you set these in x10_listener.php correctly?\n\n";
	exit;
};

if (socket_listen($sock, 5) === false) {
    echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
};

do {
    if (($msgsock = socket_accept($sock)) === false) {
        echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
        break;
    }
	$msg="OK\n";
	socket_write($msgsock, $msg, strlen($msg));
    do {
        if (false === ($buf = @socket_read($msgsock, 28, PHP_NORMAL_READ))) {
            echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)) . "\n";
            break;
        }
        if (!$buf = trim($buf)) {
            continue;
        }
	$data_out="";
	$cmd_out="";
	switch($buf) {
		case "000000000000000000000000FEA8":
			$data_out="\n";
			break;
		case "0000000000000000000060000036":
			$data_out="A1 ON\n";
			$cmd_out="on a1\n";
			break;
		case "0000000000000000000060200016":
			$data_out="A1 OFF\n";
			$cmd_out="off a1\n";
			break;
		case "0000000000000000000060100026":
			$data_out="A2 ON\n";
			$cmd_out="on a2\n";
			break;
		case "0000000000000000000060300006":
			$data_out="A2 OFF\n";
			$cmd_out="off a2\n";
			break;
		case "000000000000000000006008003E":
			$data_out="A3 ON\n";
                        $cmd_out="on a3\n";
                        break;
		case "000000000000000000006028001E":
                        $data_out="A3 OFF\n";
                        $cmd_out="off a3\n";
                        break;
		case "000000000000000000006018002E":
                        $data_out="A4 ON\n";
                        $cmd_out="on a4\n";
                        break;
		case "000000000000000000006038000E":
                        $data_out="A4 OFF\n";
                        $cmd_out="off a4\n";
                        break;
		default:
			echo "WARNING: Command received that we didn't decode. Message was:\n";
			echo "'$buf'\n\n";
			break;
	};
        if ($buf == 'quit') {
            break;
        }
        if ($buf == 'shutdown') {
            socket_close($msgsock);
            break 2;
        }
	if ($data_out) {
	        socket_write($msgsock, $data_out, strlen($data_out));
        	echo "$buf\n";
	};
	if ($cmd_out) {
		$cmd_return=shell_exec($heyu_path." ".$cmd_out);
		echo "Command: $cmd_return\n";
		$cmd_out="";
	};
    } while (true);
    socket_close($msgsock);
} while (true);

socket_close($sock);

?>

