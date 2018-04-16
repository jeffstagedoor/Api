<?php
require_once('../api/Environment.php');
require("config.php");

use Jeff\Api\Environment;
?>

<!DOCTYPE html>
<html lang="en">
  <head>
	<title>jeffstagedoor - API Dummy App</title>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<!-- Bootstrap CSS -->
	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	<!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous"> -->


</head>
<body>
	<nav class="navbar navbar-default">

	  <a class="navbar-brand" href="#">jeffstagedoor\API DUMMY APP</a>

	  <!-- <div class="collapse navbar-collapse" id="navbarsExampleDefault">
		<ul class="navbar-nav mr-auto">
		  <li class="nav-item active">
			<a class="nav-link" href="#">Home <span class="sr-only">(current)</span></a>
		  </li>
		  <li class="nav-item">
			<a class="nav-link" href="#">Link</a>
		  </li>
		  <li class="nav-item">
			<a class="nav-link disabled" href="#">Disabled</a>
		  </li>
		  <li class="nav-item dropdown">
			<a class="nav-link dropdown-toggle" href="http://example.com" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Dropdown</a>
			<div class="dropdown-menu" aria-labelledby="dropdown01">
			  <a class="dropdown-item" href="#">Action</a>
			  <a class="dropdown-item" href="#">Another action</a>
			  <a class="dropdown-item" href="#">Something else here</a>
			</div>
		  </li>
		</ul> -->
	</nav>


	<div id="main" class="container">
		<!-- <div class="row"> -->
		<h2>Dummy App</h2>
		<p>for <a href='https://github.com/jeffstagedoor/Api'>jeffstagedoor\Api</a></p>
		
		<small>AppRootFile: <?php echo __DIR__ ?></small><br>
		<a role="button" data-toggle="collapse" data-target="#env-json" aria-expanded="false" aria-controls="env-json">Environment</a><br>
		<div id="env-json" class="collapse"><?php echo "<pre>Environment as defined in config.php\n".stripslashes(json_encode(Environment::getConfig(), JSON_PRETTY_PRINT))."</pre>"; ?></div>


		<a role="button" data-toggle="collapse" data-target="#paths" aria-expanded="false" aria-controls="paths">Paths</a><br>
		<?php 
		echo "<pre id='paths' class='collapse'>";
		echo "This Script: ".$_SERVER['PHP_SELF'];
		echo "<br>AppRootUrl: ".Environment::$dirs->appRoot;
		echo "<br>ApiUrl: ".Environment::$urls->apiUrl;
		echo "</pre>";
		?>

		<a role="button" data-toggle="collapse" data-target="#ApiTest" aria-expanded="false" aria-controls="ApiTest">Api-Test &amp; Info</a><br>
		<?php
			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, Environment::$urls->baseUrl.Environment::$urls->apiUrl."/apiInfo"); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$output = curl_exec($ch); 
			echo curl_error($ch);
			echo "<pre class='collapse' id='ApiTest'>".json_encode(json_decode($output), JSON_PRETTY_PRINT)."</pre>";
			curl_close($ch);
		?>
	<br>
	<div class="panel panel-default">
		<div class="panel-body">
			<div class="col-md-6">
	<form>
	  <div class="form-group">
		<label for="httpMethod">Method</label>
		<select class="form-control" id="httpMethod">
		  <option>GET</option>
		  <option>POST</option>
		  <option>PUT</option>
		  <option>DELETE</option>
		  <option>OPTIONS</option>
		</select>
	  </div>
	  <div class="form-group">
		<label for="recource">Recource</label>
		<input type="text" class="form-control" id="recource" name="recource" placeholder="Enter email" value="posts">
		<small id="emailHelp" class="form-text text-muted">The name of the recource to be fetched/modified</small>
	  </div>


	  <div class="form-group">
		<label for="exampleTextarea">Post-Data</label>
		<textarea class="form-control" id="postData" rows="3">{"post": {"title": "der Titel"}}</textarea>
	  </div>

	  
	  <div class="form-check">
		<label class="form-check-label">
		  <input type="checkbox" class="form-check-input">
		  Check me out
		</label>
	  </div>
	  <div class="form-group">
		<label for="exampleInputPassword1">Password</label>
		<input type="password" class="form-control" id="exampleInputPassword1" placeholder="Password">
	  </div>
	  <button type="button" id="formSubmit" class="btn btn-primary">Do it</button>
	</form>
	</div>
		<div class="col-md-6">
		<div id="response">
			
		</div>
		</div>
	</div>
	</div>



	</div> <!-- /container -->



	<!-- jQuery first, then Tether, then Bootstrap JS. -->
	<!-- <script src="https://code.jquery.com/jquery-3.1.1.slim.min.js" integrity="sha384-A7FZj7v+d/sdmMqp/nOQwliLvUsJfDHW+k9Omg/a/EheAdgtzNs3hpfag6Ed950n" crossorigin="anonymous"></script> -->
	<script
  src="https://code.jquery.com/jquery-3.2.1.min.js"
  integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
  crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
	<!-- <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script> -->
	<script type="text/javascript">
		// application code here!

	  $().ready(function() {

		$('#formSubmit').on('click', function(e) {
			e.preventDefault();

			// collect what we've got.
			var method = $('#httpMethod').val();
			var recource = $('#recource').val();
			var dataString = $('#postData').val();
			if(isJSON(dataString)) {
				dataJson = JSON.parse(dataString);
			} else {
				alert('this isn\'t valid json in post-data!');
				return false;
			}

			$.ajax({
				url: 'api/'+recource,
				type: method,
				data: dataJson,
				error: function(jqXHR,textStatus, errorThrown) {
					console.log(jqXHR);

					var html = "<div class='alert alert-warning'><strong>Error: </strong>"+textStatus+' '+errorThrown+'<br>http status: '+jqXHR.status+'</div>';
					var response = '';
					if(jqXHR.responseText) {
						if(isJSON(jqXHR.responseText)) {
						var responseJson = JSON.parse(jqXHR.responseText);
						responseText = JSON.stringify(responseJson, null, 2);
						} else {
							responseText = jqXHR.responseText;
						}
						response = "<pre id='response'>"+responseText+"</pre>";
					}
					html += response;
					// html = '<div class="panel panel-default">'+html +response+'</div>';
					$('#response').html(html);
				},
				success: function(data, textStatus, jqXHR) {
					var html = "<div class='alert alert-success'><strong>success: </strong>"+textStatus+'<br>http status: '+jqXHR.status+'</div>';

					var response = '';
					if(typeof(data)==='object') {
						console.log(data);
						responseText = JSON.stringify(data, null, 2);
					}
					else if(typeof(data)==='string' && isJSON(data)) {
						var responseJson = JSON.parse(data);
						console.log(responseJson);
						responseText = JSON.stringify(responseJson, null, 2);
					} 
					else {
						responseText = data;
					}
					response = "<pre id='response' class='pre-scrollable'>"+responseText+"</pre>";
					html += response;
					$('#response').html(html);
				}
			})
				

		});

	  });


		// helpers
		function isJSON(str) {
			try {
				JSON.parse(str);
			} catch (e) {
				return false;
			}
			return true;
		}

	</script>
  </body>
</html>