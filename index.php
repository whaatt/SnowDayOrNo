<?php
	//all of the following code corresponds to
	//an hourly cron job accessing this page
	
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://www.wral.com/weather/closings/?category=all&filter=w");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    curl_close($ch);
	
	$status = file_get_contents('status.txt');
	date_default_timezone_set('US/Eastern');
	$timeNow = (int) date('H'); //unused
	
	//before 4:00 PM to after 4:00 PM, change date
	if ($timeNow < 16) { $dateOut = date('m/d/y'); }
	else { $dateOut = date('m/d/y', strtotime("tomorrow")); }
	
	if (strpos($data, "<td data-title=\"Organization\">Wake County Public Schools</td>\n                    <td data-title=\"Status\">Closed") !== False){
		$output = "<p id=\"status\" class=\"yes\">YES</p>\n";
		$streak = ((int) file_get_contents('streak.txt'));
		
		//if it's been at least 24 hours or current status is not YES, then update streak; otherwise just YES
		if (time() - 86400 > (int) file_get_contents('last.txt') or file_get_contents('status.txt') !== "YES"){
			$streak = ((int) file_get_contents('streak.txt')) + 1;
			file_put_contents('streak.txt', (string) $streak);
			file_put_contents('last.txt', (string) time());
			file_put_contents('status.txt', "YES");
		}
	}
	
	//we might deduce that there's a delay but not an actual closing based on scrape
	else if (strpos($data, "<td data-title=\"Organization\">Wake County Public Schools</td>\n                    <td data-title=\"Status\">Delayed") !== False){
		$output = "<p id=\"status\" class=\"no\">DELAY</p>\n";
		$streak = 0; //reset our counter since no snow
		file_put_contents('streak.txt', (string) $streak);
		file_put_contents('last.txt', (string) time());
		file_put_contents('status.txt', "NO");
	}
	
	//early release otherwise, pretty certain assumption since there isn't much
	else if (strpos($data, "<td data-title=\"Organization\">Wake County Public Schools</td>") !== False){
		$output = "<p id=\"status\" class=\"no\">EARLY</p>\n";
		$streak = 0; //reset our counter since no snow
		file_put_contents('streak.txt', (string) $streak);
		file_put_contents('last.txt', (string) time());
		file_put_contents('status.txt', "NO");
	}
	
	else{
		$output = "<p id=\"status\" class=\"no\">NO</p>\n";
		$streak = ((int) file_get_contents('streak.txt'));
		
		//still waiting
		if ($streak > 0){
			//if we've waited 30 hours since last snow, then likely NO
			if (time() - 108000 > (int) file_get_contents('last.txt')){
				$streak = 0; //reset our counter since no snow
				file_put_contents('streak.txt', (string) $streak);
				file_put_contents('last.txt', (string) time());
				file_put_contents('status.txt', "NO");
			}
			
			else{
				$output = "<p id=\"status\" class=\"notyet\">NOT YET</p>\n";
				$status = file_get_contents('status.txt');
				file_put_contents('status.txt', "NOT YET");
				
				//if it just changed and it is still before 4:00 PM
				if ($status === "YES" and $dateOut === date('m/d/y')){
					$output = "<p id=\"status\" class=\"yes\">YES</p>\n";
					file_put_contents('status.txt', "YES"); //temporary
				}
			}
		}
		
		else{
			$streak = 0; //reset our counter since no snow
			file_put_contents('streak.txt', (string) $streak);
			file_put_contents('last.txt', (string) time());
			file_put_contents('status.txt', "NO");
		}
	}
?>
<!doctype html>
<html>
	<head>
		<title>Is WCPSS closed?</title>
		<link rel="stylesheet" href="style.css" type="text/css">
		<link rel="shortcut icon" href="http://www.skalon.com/schools/wcpss/favicon.ico">
		
		<script type="text/javascript">
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

			ga('create', 'UA-757876-9', 'skalon.com');
			ga('send', 'pageview');
		</script>
		
		<script type="text/javascript">
			window.onload = function(){
				if ('<? echo $status ?>' === 'YES'){
					//canvas init
					var canvas = document.getElementById("canvas");
					var ctx = canvas.getContext("2d");
					
					//canvas dimensions
					var W = window.innerWidth;
					var H = window.innerHeight;
					canvas.width = W;
					canvas.height = H;
					
					//snowflake particles
					var mp = 25; //max particles
					var particles = [];
					for(var i = 0; i < mp; i++)
					{
						particles.push({
							x: Math.random()*W, //x-coordinate
							y: Math.random()*H, //y-coordinate
							r: Math.random()*4+1, //radius
							d: Math.random()*mp //density
						})
					}
					
					//Lets draw the flakes
					function draw()
					{
						ctx.clearRect(0, 0, W, H);
						
						ctx.fillStyle = "rgba(200, 255, 255, 0.8)";
						ctx.beginPath();
						for(var i = 0; i < mp; i++)
						{
							var p = particles[i];
							ctx.moveTo(p.x, p.y);
							ctx.arc(p.x, p.y, p.r, 0, Math.PI*2, true);
						}
						ctx.fill();
						update();
					}
					
					//Function to move the snowflakes
					//angle will be an ongoing incremental flag. Sin and Cos functions will be applied to it to create vertical and horizontal movements of the flakes
					var angle = 0;
					function update()
					{
						angle += 0.01;
						for(var i = 0; i < mp; i++)
						{
							var p = particles[i];
							//Updating X and Y coordinates
							//We will add 1 to the cos function to prevent negative values which will lead flakes to move upwards
							//Every particle has its own density which can be used to make the downward movement different for each flake
							//Lets make it more random by adding in the radius
							p.y += Math.cos(angle+p.d) + 1 + p.r/2;
							p.x += Math.sin(angle) * 2;
							
							//Sending flakes back from the top when it exits
							//Lets make it a bit more organic and let flakes enter from the left and right also.
							if(p.x > W+5 || p.x < -5 || p.y > H)
							{
								if(i%3 > 0) //66.67% of the flakes
								{
									particles[i] = {x: Math.random()*W, y: -10, r: p.r, d: p.d};
								}
								else
								{
									//If the flake is exitting from the right
									if(Math.sin(angle) > 0)
									{
										//Enter from the left
										particles[i] = {x: -5, y: Math.random()*H, r: p.r, d: p.d};
									}
									else
									{
										//Enter from the right
										particles[i] = {x: W+5, y: Math.random()*H, r: p.r, d: p.d};
									}
								}
							}
						}
					}
					
					//animation loop
					setInterval(draw, 33);
				}
			}
		</script>
	</head>
	<body>
		<div id="middle">
			<canvas id="canvas"></canvas>
			<? echo $output; //outputs status based on WRAL ?>
			<p><a href="http://www.wcpss.net"><? echo $dateOut; ?></a><br>
			Streak: <? echo $streak; //output streak based on info ?></p><!-- Hello! -->
		</div>
	</body>
</html>