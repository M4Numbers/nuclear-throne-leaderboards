<?php 
function render($twig, $sdata = array()) {
	if (isset($_GET["hash"])) {
		$score = new Score(array("hash" => $_GET["hash"]));
		if ($score != false) {
			echo $twig->render('score.twig', array_merge($score->to_array(), $sdata));
		} else {
			echo $twig->render('404.twig', $sdata);
		}

	} else {
		echo $twig->render('404.twig', $sdata);
	}
}

function json($sdata) {
	
}
?>