<?php
class SpecialMultiLangRedirects extends SpecialPage {
	function __construct() {
		parent::__construct( 'MultiLangRedirects', 'edit', 'move' );
	}

	function execute( $par ) {
		$output = $this->getOutput();
		$this->setHeaders();

		if (  !$this->userCanExecute( $this->getUser() )  ) {
			$this->displayRestrictionError();
			return;
		}

		$db = wfGetDB( DB_SLAVE );
		$tName = $db->tableName("translations");
		
		if ( !$db->tableExists($tName) )
			$this->makeButton($output);
		
		if ($db->tableExists($tName)){
			$pager = new MultiLangRedirectsPager();
			$output->addHTML(
				$pager->getNavigationBar() . '<ol>' .
				$pager->getBody() . '</ol>' .
				$pager->getNavigationBar()
			);
		}

	}

	function makeButton($output){
		$form = new HTMLForm( array(), $this->getContext() );		
		$form->setSubmitCallback("SpecialMultiLangRedirects::createTranslationTable");
		$form->setSubmitName("create-transl-table");
		$form->setSubmitText( wfMessage("create-transl-table-button")->plain() );
		$form->show();
		if ( $form->wasSubmitted() )
			$output->addHTML( wfMessage("transl-table-created-info")->parse() );
		else
			$output->addHTML( wfMessage("transl-table-not-yet-created-info")->parse() );
	}

	static function createTranslationTable(){
		self::createTable();
		self::fillTable();

		return true;
	}

	static function createTable(){
		$db = wfGetDB( DB_SLAVE );
		$tName = $db->tableName("translations");
		$createQuery = "CREATE TABLE" . $tName . "(
			`id` int(10) unsigned NOT NULL auto_increment,
			`page_group_id` int(10) NOT NULL,
			`language` varchar(10) NOT NULL,
			`title` varchar(255) NOT NULL,
			`namespace` int(11) NOT NULL,
			PRIMARY KEY  (`id`)
			)  
		DEFAULT 
		CHARSET=utf8";
		$db->query($createQuery);
		return true;
	}

	static function fillTable(){
		global $wfPolyglotExemptNamespaces;
		$db = wfGetDB( DB_SLAVE );
		$page_namespace_conds =  "page_namespace not in (" . implode(',', $wfPolyglotExemptNamespaces) . ")";
		$res = $db->select(
			'page',  
			array( 'page_title', 'page_namespace'),  
			array( 'page_is_redirect' => '0', $page_namespace_conds) ,  
			__METHOD__,  
			array( 'ORDER BY' => 'page_title ASC' ) 
		);      

		foreach ($res as $row){
			$pageName = "";
			$pageLang = "";
			if (MultiLangEditPager::checkIfIsLangVersion($row->page_title, $pageName, $pageLang)){
				$pgid = self::getPageGroupID($pageName, $row->page_namespace);
				$translations = array('page_group_id'=>$pgid, 'language'=>$pageLang, 'title'=>$pageName, 'namespace'=>$row->page_namespace);
				$db->insert("translations", $translations);
			}
		}
		
		return true;
	}

	static function getPageGroupID($title, $namespace){
		$db = wfGetDB( DB_SLAVE );
		
		//when the table is empty
		if ($db->selectRowCount("translations") == 0)
			return 1;
		else {
			//when there is a page group already
			$res = $db->select(
				'translations',  
				array( 'page_group_id'),  
				array('title' => $title, 'namespace' => $namespace) ,  
				__METHOD__,  
				array( ) 
			);      
			
			foreach ($res as $row)
					return $row->page_group_id;
			
			//when there is not yet a page group
			$res = $db->select(
				'translations',  
				array( 'page_group_id'),  
				array( ) ,  
				__METHOD__,  
				array( 'ORDER BY' => 'page_group_id DESC' ) 
			);      
			foreach ($res as $row)
				return $row->page_group_id + 1;
		}
	}

	public static function insertIntoTableTranslations( $article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId){
		global $wgLanguageSelectorLanguages;
		$pageTitle = $article->getTitle();
		$pageName = "";
		$pageLang = "";
		$ns = $pageTitle->getNamespace();
		$db = wfGetDB( DB_SLAVE );
		$tName = $db->tableName("translations");
		$langVers = MultiLangEditPager::checkIfIsLangVersion($pageTitle->getFullText(), $pageName, $pageLang);
		if ( $langVers and $db->tableExists($tName) ){
			
			$wholeName = explode(":", $pageName, 2);
			if (count($wholeName) == 1)
				$pageName = $wholeName[0];
			else
				$pageName = $wholeName[1];
			
			$pgid = self::getPageGroupID($pageName, $ns);
			$row = array('page_group_id'=>$pgid, 'language'=>$pageLang, 'title'=>$pageName, 'namespace'=>$ns);
			//while creating page
			if ($db->selectRowCount("translations", '*', $row) == 0)
				$db->insert("translations", $row);

			$opt = $article->makeParserOptions($user);
			$links = $article->getParserOutput($opt)->getLanguageLinks();
			if ( !empty($links) ){
				foreach ($links as $item){
					$items = explode(":", $item, 2);
					$parsedLinks[$items[0]] = $items[1];
				}
				foreach ($parsedLinks as $l => $t)
					if (in_array($l, $wgLanguageSelectorLanguages)){
						$check = array('page_group_id'=>$pgid, 'language'=>$l);
						$row = array('page_group_id'=>$pgid, 'language'=>$l, 'title' => $t, 'namespace'=>$ns);
						//while inserting from output
						if ($db->selectRowCount("translations", '*', $check) == 0)
							$db->insert("translations", $row);
						else{
							//while updating from output
							$id = $db->selectField("translations", 'id', $check);
							$db->update("translations", $row, array("id" => $id) );
						}
					}
			}
		}
	}

	public static function deleteFromTableTranslations( &$article, User &$user, $reason, $id, Content $content = null, LogEntry $logEntry ) {
		$pageTitle = $article->getTitle();
		$pageName = "";
		$pageLang = "";
		$langVers = MultiLangEditPager::checkIfIsLangVersion($pageTitle->getFullText(), $pageName, $pageLang);
		$ns = $pageTitle->getNamespace();
		$pgid = self::getPageGroupID($pageName, $ns);

		$db = wfGetDB( DB_SLAVE );
		$row = array('page_group_id'=>$pgid, 'language'=>$pageLang, 'title'=>$pageName, 'namespace'=>$ns);
		$db->delete("translations", $row);
	}

	protected function getGroupName() {
		return 'pagetools';
	}
}

class MultiLangRedirectsPager extends AlphabeticPagerWithForm {
	function getQueryInfo() {
		$id = $this->getPageID();
		return array(
			'tables' => 'translations',
			'fields' => array( '*' ),
			'conds' => array( 'id' => $id )
		);
	}

	function getPageID(){
		$result = array();
		$helparray = array();
		$db = wfGetDB( DB_SLAVE );
		$res = $db->select(
			'translations',  
			array( 'id', 'page_group_id' ),  
			array( ) ,  
			__METHOD__,  
			array( ) 
		);      
		foreach ($res as $row){
			if ( !in_array($row->page_group_id, $helparray) ){
				$result[] = $row->id;
				$helparray[] = $row->page_group_id;
			}
		}
		return $result;
	}

	function getIndexField() {
		return 'title';
	}

	function formatRow( $row ) {
		$output =  "<li id='mlr-item-" . $row->id . "'>";
		$db = wfGetDB( DB_SLAVE );
		$res = $db->select(
			'translations',  
			array( '*' ),  
			array( 'page_group_id' => $row->page_group_id ) ,  
			__METHOD__,  
			array( ) 
		);      
		foreach ($res as $newRow){
			$output .= "<div>";
			$title = Title::makeTitle($newRow->namespace, $newRow->title . "/" . $newRow->language);
			$link = Linker::link($title);
			$output .= $link;

			$formDescriptor['info-' . $newRow->language] = array(
				'default' => $newRow->page_group_id . "^" . $newRow->namespace . "^" . $newRow->title . "^" . $newRow->language,
				'class' => 'HTMLHiddenField',
				'readonly' => true
				);

			$formDescriptor["title-" . $newRow->page_group_id . $newRow->language] = array(
				'default' => $newRow->title,
				'class' => 'HTMLTextField',
				'label' => Language::fetchLanguageName($newRow->language),
				);
		}

		$form = new HTMLForm( $formDescriptor, $this->getContext() );	
		$form->setAction( $this->getTitle()->getLocalURL( $this->getRequest()->getQueryValues() ) . "#mlr-item-" . $row->id );
		$form->setSubmitText( wfMessage("edit-translation-button")->plain() );
		$form->setSubmitCallback( "MultiLangRedirectsPager::editTranslation" );

		$submitName = "submit-" . $newRow->page_group_id;
		$submitSuccessMsg = wfMessage("translation-edited")->plain();

		$output .= parent::displayRowForm($form, $submitName, $submitSuccessMsg) . "</div>";

		$output .= "</li>";
		return $output;
	}

	static function editTranslation($formData){
		$db = wfGetDB( DB_SLAVE );
		for ($i = 0; $i < count($formData); $i++){
			if ($i % 2 == 0){
				$data = array_values($formData)[$i];
				$info = explode("^", $data);
				$pgid = $info[0];
				$ns = $info[1];
				$oldTitleText = $info[2];
				$lang = $info[3];
			}
			else {
				$newTitleText = array_values($formData)[$i];
				$row = array('title' => $newTitleText);
				$db->update("translations", $row, array("page_group_id" => $pgid, "language" => $lang) );
				
				$newTitle = Title::makeTitle($ns, $newTitleText . "/" . $lang);
				$oldTitle = Title::makeTItle($ns, $oldTitleText . "/" . $lang);
				if ($newTitle->getFullText() != $oldTitle->getFullText()){
					self::movePageContent($oldTitle, $newTitle);
					self::movePageResponsibility($oldTitleText, $newTitleText);
				}
			}
		}
		return true;	
	}

	static function movePageContent($ot, $nt){
		$mp = new MovePage($ot, $nt);
		$wp = new WikiPage($ot);
		$userID = $wp->getUser();
		$user = User::newFromId($userID);
		$mp->move($user, "", true);
	}

	static function movePageResponsibility($oldPage, $newPage){
		$db = wfGetDB( DB_SLAVE );
		$tName = $db->tableName("zodpovedaza");
		$user = $db->selectField($tName, 'user_login', array('page_id'=>$oldPage));
		$db->insert($tName, array("page_id"=>$newPage, "user_login"=>$user));
	}
}