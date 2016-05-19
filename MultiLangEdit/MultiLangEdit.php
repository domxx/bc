<?php
/**
 * MultiLangEdit extension
 *
 * @file
 * @ingroup Extensions
 *
 * 
 * Usage: Add the following line in LocalSettings.php:
 * require_once( "$IP/extensions/MultiLangEdit/MultiLangEdit.php" );
 *
 * @author Dominik Bencko <bencko3@uniba.sk>
 *
 * @copyright Public domain
 * @license Public domain
 */

// Check environment
if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This is an extension to the MediaWiki package and cannot be run standalone.\n";
	die( -1 );
}

/* Configuration */

// Credits
$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'MultiLangEdit',
	'author'         => 'Dominik Bencko',
	'description'    => 'Reminds editor of editing page in other languages.',
	'descriptionmsg' => 'MultiLangEdit-desc',
);

// Shortcut to this extension directory
$dir = __DIR__ . '/';

// Internationalization
$wgMessagesDirs['MultiLangEdit'] = $dir . 'i18n';

// Register auto load for the special page class
$wgAutoloadClasses['MultiLangEditHooks'] = $dir . 'MultiLangEdit.hooks.php';
$wgAutoloadClasses['SpecialMultiLangEdit'] = $dir . 'SpecialMultiLangEdit.php';
$wgAutoloadClasses['SpecialMultiLangRedirects'] = $dir . 'SpecialMultiLangRedirects.php';
$wgAutoloadClasses['AlphabeticPagerWithForm'] = $dir . 'AlphabeticPagerWithForm.php';
$wgAutoloadClasses['MultiLangEditPager'] = $dir . 'SpecialMultiLangEdit.php';

// Register hook
$wgHooks['EditPage::showEditForm:initial'][] = 'MultiLangEditHooks::onEditPageBeforeForm';
$wgHooks['EditFormPreloadText'][] = 'MultiLangEditHooks::onEditFormPreloadText';
$wgHooks['PageContentSaveComplete'][] = 'MultiLangEditHooks::onPageContentSaveComplete';
$wgHooks['PageContentSaveComplete'][] = 'SpecialMultiLangRedirects::insertIntoTableTranslations';
$wgHooks['ArticleDeleteComplete'][] = 'SpecialMultiLangRedirects::deleteFromTableTranslations';

//Register Specialpage
$wgSpecialPages['MultiLangEdit'] = 'SpecialMultiLangEdit';
$wgSpecialPages['MultiLangRedirects'] = 'SpecialMultiLangRedirects';