<?php
if(isset($_GET['pwd'])) {
	echo password_hash($_GET['pwd'], PASSWORD_DEFAULT);
}