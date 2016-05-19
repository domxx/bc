<?php

abstract class AlphabeticPagerWithForm extends AlphabeticPager {
	
	function displayRowForm($form, $name, $successMsg){
		$form->setSubmitName($name);
		$form->prepareForm();

		if ( !is_null($form->getRequest()->getVal($name)) )
			$result = $form->tryAuthorizedSubmit();
		else 
			$result = "";
		
		if ( !($result === true || ( $result instanceof Status && $result->isGood() ) ) ) 
			$output = $form->getHTML( $result );
		else
			$output = "<p><b>" . $successMsg . "</b></p>";

		return $output;
	}
}