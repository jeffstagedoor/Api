<?php
/**
*	Class MailerPrototype
*
*	to be extended by consuming app as "Mailer"
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
*	wrapper-class to generate custom mails
*
**/

namespace Jeff\Api;

if (!defined('PHP_EOL')) define ('PHP_EOL', strtoupper(substr(PHP_OS,0,3) == 'WIN') ? "\r\n" : "\n");


Class MailerPrototype {
	const FROM_SYSTEM = "system@mystagedoor.de";
	const FROM_NOREPLY = "no-reply@mystagedoor.de";
	const FROM_AUDTION = "system@myaudition.de";

	const TO_ADMINS = "admins@mystagedoor.de";

	private $type = null;
	private $to = null;
	private $from = null;

	/** CONSTRUCTOR
	*   just get the passed classes right.
	*
	*/
	public function __construct($db, $account=null) {
		$this->db = $db;
		$this->account = $account;
	}

	public function send($from, $recipients, $subject, $content) {
		if(isset($recipients) && is_array($recipients)) {
			$to = Array();
			for ($i=0; $i < count($recipients); $i++) { 
				$to[]  = $this->_address($recipients[$i]);
			}
		}
		$headers = $this->_headers($from, $to, true);
		$success = mail(implode(',', $to), $subject, $content, $headers);
		return $success;
	}


	private function _sendOLD($type, $to, $subject="", $text="", $data=null, $html=false, $from=null, $cc=null, $bcc=null) {
		$success = true;
		switch ($type) {
			case "user2workgrouprequest":
				if($data===null) exit; // may throw an error here..
				$from = new \stdClass();
				$from->name = "myStagedoor";
				$from->email = $this->FROM_SYSTEM;
				$subject = "Workgroup Connection Request";
				$text = "Hello!<br><br>\n\n";
				$text.= $data->userName." wants to get connected to the workgroup ".$data->workgroupName.".<br>\n";
				$text.= "If you agree, that ".$data->userName." should be a member of this workgroup <a href=\"".Environment::$urls->baseUrl.Environment::$urls->tasksUrl."user2workgroupconfirmation?code=".$data->verifyCode."&workgroupId=".$data->workgroupId."&userId=".$data->userId."\">click here</a> to verify that!<br>\n";
				$text.= "In case you have no clue what this is all about, just ignore it and don't do anything.<br>\n";
				$text.= "You may contact www.mystagedoor.de (<a href=\"mailto:service@mystagedoor.com\">service@mystagedoor.com</a>) if you happen to receive more than one email with this request to stop that.<br>\n";
				$text.= "<br>\ngreetings, myStagedoor<br><br>\n";
				if(isset($data->recipients) && is_array($data->recipients)) {
					$to = Array();
					for ($i=0; $i < count($data->recipients); $i++) { 
						$to[]  = $this->_address($data->recipients[$i]);
					}
				}
				$headers = $this->_headers($from, $to, true);
				if(Environment::$production || Environment::$debug) {
					$success = mail( explode(',',$to), $subject, $text, $headers);
				}
				return $success;
				break;

			default: 
				break;
		}

	}



	// HEADERS (From, To, HTML, CC, BCC)
	private function _headers($from, $to, $html=false, $cc=null, $bcc=null) {
		$headerFrom = "";
		$headerTo = "";
		$headerCc = "";
		$headerBcc = "";
		$headerMIME = "";

		// FROM
		$headerFrom = "FROM:".$this->_address($from). PHP_EOL;
		// TO
		$headerTo = $this->_addresses("To", $to);
		// CC
		if($cc) {
			$headerCc = $this->_addresses("Cc", $cc);
		}
		// BCC
		if($bcc) {
			$headerBcc = $this->_addresses("Bcc", $bcc);
		}
		// MIME
		if($html==true) {
			$headerMIME  = "MIME-Version: 1.0" . PHP_EOL;
			$headerMIME .= "Content-type: text/html; charset=UTF-8" . PHP_EOL;
		} else {
			$headerMIME = "";
		}
		return $headerFrom.$headerTo.$headerCc.$headerBcc.$headerMIME;
	}

	private function _addresses($what, $item) {
		$arr = Array();
		if(gettype($item)==="array") {
			foreach ($item as $key => $value) {
				$arr[] = $this->_address($value);
			}
			$x = $what.": ".implode(",",$arr).PHP_EOL;
		} else {
			$x = $what.": ".$this->_adress($item).PHP_EOL;
		}
		return $x;
	}

	private function _address($item) {
		if(gettype($item)==="object") {
			return $item->name." <".$item->email.">";
		} else {
			return $item;
		}
	}

}