<?php 
	function render($twig, $sdata = array()) {
		echo $twig->render('about.twig', $sdata);
	}
	
function json($sdata) {
	
}
?>