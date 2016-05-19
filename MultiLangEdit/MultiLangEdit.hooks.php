<?php
class MultiLangEditHooks {
	
	public static function onEditPageBeforeForm ( EditPage $editPage, OutputPage $output ) { 
		
 		global $wgLanguageSelectorLanguages;

 		$title = $editPage->getTitle();
 		$pageTitle = $title->getText();
 		$pageNs = $title->getNamespace();

 		$edit = "";

 		if ($title->exists()) {
 			$edit .= "<h2>" . wfMessage( 'edithead' )->plain() . "</h2>";
 			$edit .= "<p>" . wfMessage( 'editnote' )->plain() ;
 		}
 		else {
 			$edit .= "<h2>" . wfMessage( 'createhead' )->plain() . "</h2>";
 			$edit .= "<p>" . wfMessage( 'createnote' )->plain() ;	
 		}
 		
 		$languages = $wgLanguageSelectorLanguages;

 		$pageName = "";
 		$pageLang = "";
	  	if ( MultiLangEditPager::checkIfIsLangVersion($pageTitle, $pageName, $pageLang) ){
	  		$key = array_search($pageLang, $languages);
	  		unset($languages[$key]);	
	  	}

		$db = wfGetDB( DB_SLAVE );
 		foreach ($languages as $lang) {
 			
 			$tName = $db->tableName("translations");
 			$fullLang = Language::fetchLanguageName($lang);
			
			if ( $db->tableExists($tName) ){
	 			$pgid = SpecialMultiLangRedirects::getPageGroupID($pageName, $pageNs );
				$res = $db->select(
					'translations',  
					array( 'language', 'title' ),  
					array('page_group_id' => $pgid, 'language' => $lang),  
					__METHOD__,  
					array( ) 
				);     
				$row = array('page_group_id'=>$pgid, 'language'=>$lang);
				if ($db->selectRowCount("translations", '*', $row) != 0){
					foreach ($res as $row){
						$newTitle = Title::makeTitle($pageNs, $row->title . "/" . $row->language);
						$link = Linker::link($newTitle, $fullLang);
						$edit .= " " . $link;
					}
				}
				else {
					$newTitle = Title::makeTitle($pageNs, $pageName . "/" . $lang);
					$link = Linker::link($newTitle, $fullLang);
					$edit .= " " . $link;
				}
			}
			else {
				$newTitle = Title::makeTitle($pageNs, $pageName . "/" . $lang);
				$link = Linker::link($newTitle, $fullLang);
				$edit .= " " . $link;
			}
 		}
		
		$edit .= "</p><hr />";
		$editPage->editFormPageTop .= $edit; 
	}

	public static function onEditFormPreloadText( &$text, &$title ) { 
		$pageTitle = $title->getFullText();
		$pageName = "";
		$pageLang = "";
	  	if ( !MultiLangEditPager::checkIfIsLangVersion( $pageTitle, $pageName, $pageLang ) )
			$text = wfMessage('default-languageless-content')->plain();
	}

	public static function onPageContentSaveComplete( $article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId ) { 

		global $wgCreateLangLessPage;

		$pageTitle = $article->getTitle()->getFullText();
		$pageName = "";
		$pageLang = "";

 		if ( MultiLangEditPager::checkIfIsLangVersion( $pageTitle, $pageName, $pageLang ) ) {
			switch ($wgCreateLangLessPage) {
				case "always":		
					self::createPage( $pageName );
		 			break;
		 		case "on-create":
		 			$newTitle = Title::newFromText( $pageTitle );
		 			if ( $newTitle->isNewPage() )
						self::createPage( $pageName );
			 		break;
		 		case "never":
		 			break;
			}
  		}
	}

	static function createPage($title){
		$newTitle = Title::newFromText($title);
	 	$page = WikiPage::factory($newTitle);
	 	$flags = EDIT_NEW;
	 	$content = $page->getContentHandler();
	 	$text = wfMessage('default-languageless-content')->plain();
	 	$newContent = $content->makeContent($text, $newTitle);
 		return $page->doEditContent( $newContent, "" );
	}

}
