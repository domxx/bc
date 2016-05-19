<?php
class SpecialMultiLangEdit extends SpecialPage {
	function __construct() {
		parent::__construct( 'MultiLangEdit', 'edit', 'move' );
	}

	function execute( $par ) {
		$output = $this->getOutput();
		$this->setHeaders();

		if (  !$this->userCanExecute( $this->getUser() )  ) {
			$this->displayRestrictionError();
			return;
		}

		$pager = new MultiLangEditPager();
		$output->addHTML(
			$pager->getNavigationBar() . '<ol>' .
			$pager->getBody() . '</ol>' .
			$pager->getNavigationBar()
		);

	}

	protected function getGroupName() {
		return 'pagetools';
	}
}

class MultiLangEditPager extends AlphabeticPagerWithForm {

	function getQueryInfo() {
		$allPages = $this->getAllPages();
		$filteredPages = $this->getFilteredPages($allPages); // filtered out pages not missing any version
		$filteredLangPages = $this->getFilteredLangPages($filteredPages); //filtered out language versions of pages if languageless version exists
		$finalListOfPages = $this->getFinalListOfPages($filteredLangPages); //filtered out more lang versions of "same" title, left only one
		global $wfPolyglotExemptNamespaces;
		$page_namespace_conds =  "page_namespace not in (" . implode(',', $wfPolyglotExemptNamespaces) . ")";
		return array(
			'tables' => 'page',
			'fields' => array('page_id', 'page_title', 'page_namespace'),
			'conds' => array( 'page_is_redirect' => '0', $page_namespace_conds, 'page_title' => $finalListOfPages )
		);
	}

	function getAllPages(){
		global $wfPolyglotExemptNamespaces;
		$result = array();

		$dbr = wfGetDB( DB_SLAVE );
		$page_namespace_conds =  "page_namespace not in (" . implode(',', $wfPolyglotExemptNamespaces) . ")";
		$res = $dbr->select(
			'page',  
			array( 'page_title', 'page_namespace'),  
			array( 'page_is_redirect' => '0', $page_namespace_conds) ,  
			__METHOD__,  
			array( 'ORDER BY' => 'page_title ASC' ) 
		);      
		
		foreach ($res as $row)
			array_push($result, array($row->page_namespace, $row->page_title));

		return $result;
	}

	function getFilteredPages($pages){
		 global $wgLanguageSelectorLanguages;
		$result = array();

		foreach ($pages as $q){
			$page = $q[1];
			$namespace = $q[0];
			$doWrite = true;
		
			$pageName = "";
			$pageLang = "";
			$isLangVersion = $this->checkIfIsLangVersion( $page, $pageName, $pageTitle );

			if ($isLangVersion){
				$everyLangVersionsExist = true;
				$noLangVersionExists = Title::makeTitle($namespace, $pageName) -> exists() ? true : false;
				foreach ($wgLanguageSelectorLanguages as $lang){
					if (!Title::makeTitle($namespace, $pageName . "/" . $lang) -> exists()){
						$everyLangVersionsExist = false;
						break;
					}
				}

				$doWrite = ($everyLangVersionsExist and $noLangVersionExists) ? false : true;
				if ($doWrite)
					array_push($result, $page);
			}
			else{
				$everyLangVersionsExist = true;
				foreach($wgLanguageSelectorLanguages as $lang){
					if (!Title::makeTitle($namespace, $page . "/" . $lang) -> exists()){
						$everyLangVersionsExist = false;
						break;
					}
				}

				$doWrite = ($everyLangVersionsExist) ? false : true;
				if ($doWrite)
					array_push($result, $page);
			}
		}

		return $result;
	}

	function getFilteredLangPages($pages){
		foreach($pages as $page){
			$pageName = "";
			$pageLang = "";
			$isLangVersion = $this->checkIfIsLangVersion( $page, $pageName, $pageLang );

			if ($isLangVersion and in_array($pageName, $pages)){
				$key = array_search($page, $pages);
				unset($pages[$key]);
			}
		}

		return $pages;
	}

	function getFinalListOfPages($pages){
		$result = array();
		$resultHelper = array();

		foreach ($pages as $page){
			$pageName = "";
			$pageLang = "";
			$isLangVersion = $this->checkIfIsLangVersion($page, $pageName, $pageLang);

			if ($isLangVersion){
				if (!in_array($pageName, $resultHelper)){
					array_push($resultHelper, $pageName);
					array_push($result, $page);
				}
			}
			else
				array_push($result, $page);
		}

		return $result;
	}	

	static function checkIfIsLangVersion($page, &$title, &$lang){
		global $wgLanguageSelectorLanguages;
		if ( preg_match('!(.+)/(\w[-\w]*\w)$!', $page, $data) ) {
			$title = $data[1];
			$lang = $data[2];
			if (in_array($lang, $wgLanguageSelectorLanguages))
				return true;
		}
		$title = $page;
		return false;
	}

	function getIndexField() {
		return 'page_title';
	}

	function formatRow( $row ) {
		$title = Title::newFromRow( $row );
		$pageTitle = $title->getFullText();
		$pageNs = $title->getNamespace();
		
		$pageName = "";
		$pageLang = "";
		$isLangVersion = $this->checkIfIsLangVersion($pageTitle, $pageName, $pageLang);
		list($existing, $missing) = $this->fillArrays($pageTitle, $isLangVersion, $pageName, $pageNs);
		
		$allPagesExist = $this->checkIfAllPagesExist($title);
		if (!$allPagesExist){
			$output = "<li id='mle-item-".$row->page_id."'>" . Linker::link ( $title, $html = null, $customAttribs = [], $query = [], $options = [] ) . "<br />";

			if ($isLangVersion){			
				$id = $row->page_id;
				$submitName = "submit-" . $id;
				$submitSuccessMsg = wfMessage("languagelesspagecreated")->plain();

				$formDescriptor['text-' . $id] = array(
					'type' => 'hidden',
					'default' => $pageName,
					'class' => 'HTMLHiddenField',
					'readonly' => true
					);
				$form = new HTMLForm( $formDescriptor, $this->getContext() );
				
				$form->setAction( $this->getTitle()->getLocalURL( $this->getRequest()->getQueryValues() ) . "#mle-item-" . $id );

				$form->setSubmitText( wfMessage("createlanglesspagebutton")->plain() );
				$form->setSubmitCallback( "MultiLangEditPager::createLangLessPage" );

				$output .= parent::displayRowForm($form, $submitName, $submitSuccessMsg);
		       	}

		       	$output .= wfMessage("existingversions")->parse() . implode(', ', $existing) . "<br />";
		       	$output .= wfMessage("missingversions")->parse() . implode(', ', $missing) . "</li>";
		}
		else
			$output = "";
		
		return $output;
	}

	function checkIfAllPagesExist($title){
		$pageTtl = $title->getFullText();
		$pageNs = $title->getNamespace();
		global $wgLanguageSelectorLanguages;
		$allPagesExist = true;
		foreach ($wgLanguageSelectorLanguages as $lang){
			if ( !Title::makeTitle( $pageNs, $pageTtl . "/" . $lang)->exists() ){
				$allPagesExist = false;
				break;
			}
		}
		return $allPagesExist;
	}

	function fillArrays($title, $isLangVersion, $pageName, $pageNs){
		$db = wfGetDB( DB_SLAVE );
		global $wgLanguageSelectorLanguages;
		$existing = array();
		$missing = array();
		foreach($wgLanguageSelectorLanguages as $lang){
			if ($isLangVersion)
				$page = self::checkTranslation($pageName, $lang, $pageNs);
			else
	       			$page = self::checkTranslation($title, $lang, $pageNs);

	       		$fullLang = Language::fetchLanguageName($lang);
	       		$link = Linker::link($page, $html = $fullLang);
	       		if ( $page ->exists() )
	       			array_push($existing, $link);
	       		else
	       			array_push($missing, $link);
		}
		return array($existing, $missing);
	}

	static function checkTranslation($title, $lang, $pageNs){
		$db = wfGetDB( DB_SLAVE );
		$tName = $db->tableName("translations");
		if ( $db->tableExists($tName) ){
			$pgid = SpecialMultiLangRedirects::getPageGroupID($title, $pageNs);
			$row = array('page_group_id'=>$pgid, 'title'=>$title, 'namespace'=>$pageNs);
			if ($db->selectRowCount("translations", '*', $row) == 1){
				$ttl = $db->selectField("translations", 'title', $row);
				return Title::newFromText( $ttl . "/" . $lang);
			}
		}
		
		return Title::newFromText( $title . "/" . $lang);
	}

	static function createLangLessPage($formData){
		return MultiLangEditHooks::createPage( array_values($formData)[0] );
	}

}
