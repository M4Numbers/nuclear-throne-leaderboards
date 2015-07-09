<?php
	function render($twig, $sdata = array()) {
		echo $twig->render('help.twig', $sdata);
	}

function json($sdata) {

}
?>
