<?php
/**
 * only a helper to generate password hashs.
 * shall not be in production code...
 */
if(isset($_GET['pwd'])) {
	echo password_hash($_GET['pwd'], PASSWORD_DEFAULT);
}