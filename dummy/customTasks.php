<?php
/*
/	This File must only be included from api/tasks.php as part of Jeff's API
/
/	This File containes special tasks to be called like www.myserver.com/api/task/doSomething
*/

switch ($type) {

	/*
	*	USER2ARTISTREQUEST
	*	when an user wants to get linked to an artist, frontend will send a post request to api/tasks/user2artistrequst
	*
	*/

	case 'user2artistrequest':
		// save notification/task
		$code = GetRandomString(80);

		// find workgroup that might be interested in that request:
		// search for the first workgroup the user is connected to
		// $db->where('user', $data->userId);
		// $u2w = $db->get('user2workgroup');
		// if($u2w) {

		// }
		// march 2016: decided not to do that for now. Only site-Admins may connect users to artists.

		$dbData = Array(
			"code" => $code,
			"type" => $type,
			"meta1" => $data->userId,
			"meta2" => $data->artistId,
			"forUserRights" => 2,
			"forWorkgroup" => 0,
			"forWorkgroupRights" => 2,
			"message" => $data->msg,
		);
		$id = $db->insert('tasks', $dbData);

		if($id) {
			
		// send email to artist to evaluate this link-request
		include_once($ENV->dirs->phpRoot.$ENV->dirs->models.'Artists.php');
		$objArtists = new Models\Artists($db);
		$artist = $objArtists->get($data->artistId);
		#var_dump($Account);
		if(isset($artist['email']) && filter_var($artist['email'], FILTER_VALIDATE_EMAIL)) {
			require_once $ENV->dirs->phpRoot."Mailer.php";
			$data = new \stdClass();
			$data->recipientName = $artist['fullName'];
			$data->userName = $Account->data->personalDetails->fullName;
			$data->userId = $Account->id;
			$data->artistId = $artist['id'];
			$data->artistName = $artist['fullName'];
			$data->verifyCode = $code;
			Mailer::send("user2artistrequest",$artist['email'], "", "", $data);
			$response = '{"success": {"taskId": '.$id.', "email": "'.$artist['email'].'"} }';
		} else {
			$response = '{"success": {"taskId": '.$id.'} }';
		}

		} else {
			$response = '{"errors": [{"msg": "internal error - could not save task"}] }';
		}
		return $response;
		break;


	
	/*
	*	USER2ARTISTCONFIRMATION
	*	this is the callback from email-notifications when an user sends a request to get linked to an artist.
	*	The artist then gets an email with a verification link, wich leads him here - via ..api/tasks
	*
	*/
	case 'user2artistconfirmation':
		// first check if this is a valid request.
		// so get the correspondend dataset from tasks-table
		if(!isset($data->code) || !isset($data->userId) || !isset($data->artistId)) {
			$response = '{"errors": [{"msg": "no valid request"}] }';
			gotoErrorPage($response, $type);
			exit;
		}
		$db->where('code', $data->code);
		$db->where('meta1', $data->userId);
		$db->where('meta2', $data->artistId);
		$dbTask  = $db->getOne('tasks');
		if(!$dbTask) {
			$response = '{"errors": [{"msg": "no matching task available"}] }';
			gotoErrorPage($response, $type);
			exit;	
		}
		// check if there is a user connected to this artist:
		$db->where('artist',$data->artistId);
		$alreadyConnected  = $db->getOne('users', Array('id','fullName'));
		if($alreadyConnected) {
			$response = '{"errors": [{"msg": "artist is already connected to user '.$alreadyConnected['fullName'].' (id '.$alreadyConnected['id'].')"}] }';
			gotoErrorPage($response, $type);
			exit;		
		}

		// now we got everything collected and checked, so lets start the work:
		// connect user with artist:
		$updateDataset = Array(
			"artist" => $data->artistId
		);
		$db->where('id', $data->userId);
		$db->update('users', $updateDataset);

		// update task: set fulfilled & remove code
		$updateDataset = Array(
			"code" => '',
			"fulfilled" => true,
			"resolvedDate" => $db->now(),
			"by" => $data->userId
		);
		$db->where('code', $data->code);
		$db->where('meta1', $data->userId);
		$db->where('meta2', $data->artistId);
		$db->get('tasks');
		#$success = $db->update('tasks', $updateDataset);

		// fetch some data for success-page (only):
		$db->join('users u', 'u.artist=a.id');
		$db->where('u.id',$data->userId);
		$names = $db->getOne('artists a', "u.fullName as ufullName, a.fullName as afullName");
		// redirect to success-page
		http_response_code(200);
		header("location: ".$ENV->urls->baseUrl.$ENV->urls->appUrl.'publicLinks/fulfilledRequests?type=user2artistconfirmation&userName='.urlencode($names['ufullName']).'&artistName='.urlencode($names['afullName']));
		break;

	/*
	*	USER2WORKGROUPREQUEST
	*	when an user wants to get connected to a workgroup, frontend will send a post request to api/tasks/user2workgrouprequst
	*
	*/
	case 'user2workgrouprequest':
		$minWgRights = Constants::WORKGROUPS_ADMIN;	// minimun rights for workgroup-members to be notified
		$minRights = Constants::USER_ADMIN;			// minimun rights for site-admins to be notified
		// save notification/task
		$code = GetRandomString(80);
		$dbData = Array(
			"code" => $code,
			"type" => $type,
			"meta1" => $data->userId,
			"meta2" => $data->workgroupId,
			"forUserRights" => $minRights,
			"forWorkgroup" => $data->workgroupId,
			"forWorkgroupRights" => $minWgRights,
			"message" => $data->msg,
		);
		#var_dump($dbData);
		$id = $db->insert('tasks', $dbData);

		if($id) {
			$recipients = Array();
			$recipient = new stdClass();

			// send email to workgroup-admins to decide upon this
			require_once($ENV->dirs->phpRoot.$ENV->dirs->models.'Workgroups.php');
			$objWorkgroups = new Workgroups($db);
			$wgAdmins = $objWorkgroups->getUsers($data->workgroupId, $minWgRights);
			for ($i=0; $i < count($wgAdmins); $i++) { 
				if(isset($wgAdmins['u.email']) && filter_var($wgAdmins['u.email'], FILTER_VALIDATE_EMAIL)) {
					$recipient->name = $wgAdmins[$i]['u.fullName'];
					$recipient->email = $wgAdmins[$i]['u.email'];
					$recipients[] = $recipient;
				}
			}
			// fetch workgroup name
			$workgroup = $objWorkgroups->get($data->workgroupId);
			// also add Super-Admin-Users ?

			require_once $ENV->dirs->phpRoot.$ENV->dirs->classes."Mailer.php";
			$mdata = new stdClass();
			$mdata->recipients = $recipients;
			$mdata->userName = $authUser['fullName'];
			$mdata->userId = $authUser['id'];
			$mdata->workgroupId = $data->workgroupId;
			$mdata->workgroupName = $workgroup['label'];
			$mdata->verifyCode = $code;
			$Mailer = new Mailer();
			$Mailer->send("user2workgrouprequest",$mdata->recipients, "", "", $mdata);
			
			$response = '{"success": {"taskId": '.$id.', "recipients": '.json_encode($recipients).'} }';

		} else {
			$response = '{"errors": [{"msg": "internal error - could not save task"}] }';
		}
		return $response;
		break;

	/*
	*	USER2WORKGROUPCONFIRMATION
	*	this is the callback from email-notifications when an user sends a request to get connected to a workgroup.
	*	The admins of that workgroup then gets an email with a verification link, wich leads him here - via $ENV->dirs->phpRoot..api/tasks
	*
	*/
	case 'user2workgroupconfirmation':
		// first check if this is a valid request.
		// so get the correspondend dataset from tasks-table
		if(!isset($data->code) || !isset($data->userId) || !isset($data->workgroupId)) {
			$response = '{"errors": [{"msg": "no valid request"}] }';
			gotoErrorPage($response, $type);
			exit;
		}
		$db->where('code', $data->code);
		$db->where('meta1', $data->userId);
		$db->where('meta2', $data->workgroupId);
		$dbTask  = $db->getOne('tasks');
		if(!$dbTask) {
			$response = '{"errors": [{"msg": "no matching task available"}] }';
			gotoErrorPage($response, $type);
			exit;	
		}
		// check if there is a user connected to this workgroup:
		$db->join('users u', 'u.id=u2w.user', 'LEFT');
		$db->join('workgroups w', 'w.id=u2w.workgroup', 'LEFT');

		$db->where('u2w.workgroup',$data->workgroupId);
		$db->where('u2w.user',$data->userId);
		$alreadyConnected  = $db->getOne('user2workgroup u2w', Array('u.id','u.fullName','w.label', 'u2w.rights'));
		if($alreadyConnected) {
			$response = '{"errors": [{"msg": "user '.$alreadyConnected['u.fullName'].' is already connected to workgroup '.$alreadyConnected['w.label'].' (with rights: '.$alreadyConnected['u2w.rights'].')"}] }';
			gotoErrorPage($response, $type);
			exit;		
		}

		// now we got everything collected and checked, so lets start the work:
		// connect user with workgroup:
		$insertDataset = Array(
			"workgroup" => $data->workgroupId,
			"user" => $data->userId,
			"rights" => Constants::WORKGROUPS_MEMEBER
		);
		$db->where('id', $data->userId);
		$db->update('users', $updateDataset);

		// update task: set fulfilled & remove code
		$updateDataset = Array(
			"code" => '',
			"fulfilled" => true,
			"resolvedDate" => $db->now(),
			"by" => $data->userId
		);
		$db->where('code', $data->code);
		$db->where('meta1', $data->userId);
		$db->where('meta2', $data->workgroupId);
		$db->get('tasks');
		$success = $db->update('tasks', $updateDataset);

		// fetch some data for success-page (only):
		$db->join('users u', 'u.artist=a.id');
		$db->where('u.id',$data->userId);
		$names = $db->getOne('artists a', "u.fullName as ufullName, a.fullName as afullName");
		// redirect to success-page
		http_response_code(200);
		header("location: ".$ENV->urls->baseUrl.$ENV->urls->appUrl.'publicLinks/fulfilledRequests?type=user2workgroupconfirmation&userName='.urlencode($names['ufullName']).'&workgroupName='.urlencode($names['workgroup']));
		break;


	/*
	*	INVITEUSER2WORKGROUP
	*	this is to be called from frontend when an existing user wants to invite somebody to signup and become a member of a workgroup/production
	*
	*/
	case 'inviteuser2workgroup':
		// echo "data:\n";
		// var_dump($data);
		// initialize some data in case it's not set:
		$data->name = isset($data->name) ? $data->name : "";
		$data->workgroup = isset($data->workgroup) ? $data->workgroup : null;
		$data->production = isset($data->production) ? $data->production : null;
		$data->audition = isset($data->audition) ? $data->audition : null;

		if(isset($data->email) && filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
			// get informations about workgroup & production
			$db->where('id', $data->workgroup);
			$workgroup = $db->getOne('workgroups');


			// check if the designated user has an account
			$db->where('email', $data->email);
			$user = $db->getOne('users');
			if($user) {
				$response = '{"errors": [{"msg": "is user already"}] }';
				// abort this and send back error. User should add the existing user in another way.
				return $response;
				// check if the user is connected to workgroup already:
				// $db->where('user', $user['id']);
				// $db->where('workgroup', $data->workgroup);
				// $u2w = $db->getOne('user2workgroup');
				// if($u2w) {
				// 	$response = '{"errors": [{"msg": "user is member"}] }';
				// 	return $response;
				// } 
				// $newUserId = $user['id'];
			} else {
				// kein user vorhanden
				// also senden wir ein mail an die angegebene email-adresse mit einem invitation code,
				// den wir in db->users mit einem neuen User hinterlegen
				$invitationToken = GetRandomString(60);
				$dataset = Array(
					'email'=>$data->email,
					'fullName'=>$data->name,
					'invitationToken'=>$invitationToken,
					'invitedBy'=>$authUser['id'],
					'modDate'=>$db->now(),
					'modBy'=>$authUser['id']
					);
				$newUserId = $db->insert('users', $dataset);
				// echo "added new user:\n";
				// var_dump($dataset);
				// echo "new userId: ".$newUserId."\n";
			}	// end if user exists

			// add the new or existing user to the workgroup
			if(!isset($data->workgroupRights)) {
				include_once($ENV->dirs->phpRoot.$ENV->dirs->classes.'Constants.php');
				$data->workgroupRights = Constants::WORKGROUPS_MEMBER;
			}
			$dataset = Array(
				'id'=>$newUserId.'_'.$data->workgroup,
				'user'=>$newUserId,
				'workgroup'=>$data->workgroup,
				'rights'=>$data->workgroupRights,
				'invitedBy'=>$authUser['id'],
				'invitedDate'=>$db->now(),
				'modBy'=>$authUser['id']
				);
			$db->insert('user2workgroup', $dataset);
			$response = '{"success": [{"msg": "user was added"}] }';

			// echo "adding to user2workgroup with:\n";
			// var_dump($dataset);


			// send Mail to new user
			require_once $ENV->dirs->phpRoot.$ENV->dirs->classes."Mailer.php";
			$Mailer = new Mailer();
			$mdata = new stdClass();
			$recipient = new stdClass();
			$recipient->email = $data->email;
			$recipient->name = $data->name;
			$mdata->recipient = $recipient;
			$mdata->userId = $newUserId;
			$mdata->workgroup = $workgroup['id'];
			$mdata->workgroupName = $workgroup['label'];
			$mdata->invitationCode = $invitationToken;
			$mdata->invitedById = $authUser['id'];
			$mdata->invitedByName = $authUser['fullName'];
			$Mailer->send("user2workgroupinvitation",$mdata->recipient, "", "", $mdata);

			// echo "sending mail to:\n";
			// var_dump($mdata);

			// make an entry in LOG for Notifications
			require_once($ENV->dirs->phpRoot.$ENV->dirs->classes.'Log.php');
			$log = new Log($db);
			$for = new stdClass();
			$for->workgroup = $data->workgroup;
			$for->workgroupRights = Constants::WORKGROUPS_ADMIN;
			$for->production = $data->production;
			$for->productionRights = Constants::PRODUCTIONS_ADMIN;
			$for->audition = $data->audition;
			$for->auditionRights = Constants::AUDITIONS_ADMIN;
			$for->user = $newUserId;
			$for->userRights = 3;
			//$logData = new stdClass();
			$meta = Array();
			$meta[0] = $newUserId;
			$meta[1] = $data->workgroup;
			$meta[2] = $data->workgroupRights;
			$meta[3] = $data->email;
			$meta[4] = null;

			// echo "making log-entry:\nfor:\n";
			// var_dump($for);
			// echo "logData:\n";
			// var_dump($meta);

			$logId = $log->writeLog($authUser['id'], 'user2workgroupinvitation', 'user2workgroup', $for, $meta);
			return $response;
			// echo "logId: " . $logId."\n";
		} else {	// no valid email adress, so return error msg
			$response = '{"errors": [{"msg": "no valid email"}] }';
			return $response;
		}

		break;


	default:
		exit;
}	
