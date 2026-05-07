<?php
	// $servername = "localhost";
	$servername = "sql204.infinityfree.com";
	// $username = "root";
	$username = "if0_41776263";
	// $password = "";
	$password = "Huynd2003";
	// $dbname = "review";		
	$dbname = "if0_41776263_review";	
	$connect = new mysqli($servername, $username, $password, $dbname);	
	//Nếu kết nối bị lỗi thì xuất báo lỗi và thoát.
	if ($connect->connect_error) {
	    die("Không thể kết nối :" . $connect->connect_error);
	}	
?>