<?php
/**
 * htmlEditorAnswers Plugin for LimeSurvey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014 Denis Chenu <http://sondages.pro>
 * @copyright 2014 WHO | World Health Organization <http://www.who.int>
 * @license GNU AFFERO GENERAL PUBLIC LICENSE Version 3 or later (the "AGPL")
 * @version 0.9
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * This plugin use :
 * CKEditor : Copyright (c) 2003-2014, CKSource - Frederico Knabben. All rights reserved. Released under GPL 2, LGPL 2 and MPL 1.1
 */
class htmlEditorAnswers extends PluginBase {
    protected $storage = 'DbStorage';
    
    static protected $description = 'A plugin to allow to use an HTML editor (ckeditor) for answers.';
    static protected $name = 'Html editor for answers';

    protected $settings = array(
        'questiontype' => array(
            'type' => 'select',
            'label' => 'Use the plugin by default in question type of .',
             'options'=>array(
                '0'=>'None',
                'T'=>'Long free text',
                'U'=>'Huge free text (default)',
            ),
            'default'=>'U',
            'help'=>'This setting is applied for all survey if not set by survey',
        ),
        'questionname' => array(
            'type' => 'string',
            'label' => 'Activate (by default) for all question with question code ending by ',
        ),
        'configfile' => array(
            'type' => 'select',
            'label' => 'Use config file :',
            'options'=>array(
                    'ligth'=>'Ligth',
                    'base'=>'Basic (default)',
                    'all'=>'Complete (all settings are not tested)',
            ),
            'default'=>'base',
        ),
        'cssfile' => array(
            'type' => 'string',
            'class' => 'large',
            'label' => 'Link to the css file to use (complete link, with http://)',
        ),
        'tags' => array(
            'type' => 'json',
            'label' => 'Extra format for format dropdown',
            'editorOptions'=>array('mode'=>'text'),
            'default' => '{ "p":{ "element":"p" },"pre":{ "element":"pre" } }',
        ),
    );

    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeQuestionRender');
    }

    public function beforeSurveySettings()
    {
        $oEvent = $this->event;
        $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
        Yii::app()->clientScript->registerCssFile($assetUrl . '/settingsfix.css');
        $aToolBars=array("default"=>"Use default") + $this->settings['configfile']['options']; 
        $oEvent->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'questiontype'=>array(
                    'type'=>'select',
                    'label' => 'Use the plugin by default in question type of .',
                     'options'=>array(
                        'default'=>'Use default',
                        'NA'=>'None',
                        'T'=>'Long free text',
                        'U'=>'Huge free text',
                    ),
                    'help'=>'An help text',
                    'current' => $this->get('questiontype', 'Survey', $oEvent->get('survey'),'default'),
                ),
                'questionname'=>array(
                    'type'=>'string',
                    'label' => 'Activate for all question with question code ending by ',
                    'current' => $this->get('questionname', 'Survey', $oEvent->get('survey'),$this->get('questionname')),
                ),
                'configfile' => array(
                    'type' => 'select',
                    'label' => 'Use config file :',
                    'options'=>$aToolBars,
                    'current' => $this->get('configfile', 'Survey', $oEvent->get('survey'),'default'),
                ),
                'cssfile' => array(
                    'type' => 'string',
                    'label' => 'Link to the css file to use',
                    'class' => 'large',
                    'current' => $this->get('cssfile', 'Survey', $oEvent->get('survey'),$this->get('cssfile')),
                ),
                'tags' => array(
                    'type' => 'json',
                    'label' => 'Extra format for format dropdown',
                    'editorOptions'=>array('mode'=>'text'),
                    'current' => $this->get('tags', 'Survey', $oEvent->get('survey'),$this->get('tags')),
                ),
            ),
        ));
    }

    public function newSurveySettings()
    {
        $oEvent = $this->event;
        foreach ($oEvent->get('settings') as $name => $value)
        {
            /* In order use survey setting, if not set, use global, if not set use default */
            $default=$oEvent->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL);
            $this->set($name, $value, 'Survey', $oEvent->get('survey'),$default);
        }
    }

    public function beforeQuestionRender()
    {
        $oEvent=$this->getEvent();
        $sQuestionType=$this->get('questiontype', 'Survey', $oEvent->get('surveyId'),$this->get('questiontype',null,null,$this->settings['questiontype']['default']));
        $sQuestionName=$this->get('questionname', 'Survey', $oEvent->get('surveyId'),$this->get('questionname'));
        if($sQuestionType=='default')
            $sQuestionType=$this->get('questiontype',null,null,$this->settings['questiontype']['default']);
        if( ( $sQuestionType!="NA" && $oEvent->get('type')==$sQuestionType )
            || 
            ( $sQuestionName && substr($oEvent->get('code'), -strlen($sQuestionName)) === $sQuestionName )
          )
        {
            $questionClass=$oEvent->get('class'); // Danger with other plugin
            $oQuestionBrowser=Question::model()->find("sid=:sid AND qid=:qid",array(':sid'=>$oEvent->get('surveyId'),':qid'=>$oEvent->get('qid')));
            $sAnswerId="answer".$oQuestionBrowser->sid."X".$oQuestionBrowser->gid."X".$oQuestionBrowser->qid;
            //$sScriptFile="//cdn.ckeditor.com/4.4.5/full/ckeditor.js"; // Disallow preview
            $sScriptFile=Yii::app()->baseUrl.'/plugins/htmlEditorAnswers/third_party/ckeditor/ckeditor.js'; // Allow preview
            Yii::app()->clientScript->registerScriptFile($sScriptFile);
            // Some css correction (with asset)
            $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
            Yii::app()->clientScript->registerCssFile($assetUrl . '/htmleditoranswers.css');
            // Call the config (with asset) move it to assets ? In config, seems more clear for dev user.
            $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/config');
            $sConfigFile=$this->get('configfile', 'Survey', $oEvent->get('surveyId'),$this->get('configfile',null,null,$this->settings['configfile']['default']));
            
            if($sConfigFile=='default')
                $sConfigFile=$this->get('configfile',null,null,$this->settings['configfile']['default']);
            $sLangCode= Yii::app()->lang->langcode;

            $aCkOptions=array(
                'customConfig' => "{$assetUrl}/{$sConfigFile}.js",
                'language' => $sLangCode,
            );
            $sCssFile=$this->get('cssfile', 'Survey', $oEvent->get('surveyId'),$this->get('cssfile'));
            if($sCssFile)
                $aCkOptions['contentsCss'] = $sCssFile;
            
            $sJsonTag=$this->get('tags', 'Survey', $oEvent->get('surveyId'),$this->get('tags'));
            $aTags=json_decode($sJsonTag,true);
            $aCkOptionsLang=array();
            if(!empty($aTags)){
                $aCkOptions['format_tags'] = implode(";", array_keys($aTags)); 
                foreach($aTags as $sTag => $aTag){
                    if(!empty($aTag['description']))
                        $aCkOptionsLang["tag_{$sTag}"] = $aTag['description'];
                    unset($aTag['description']);
                    $aCkOptions["format_{$sTag}"]=$aTag;
                }
            }else{
                $aCkOptions['removePlugins'] = 'format';
            }
            $jsonCkOptions=ls_json_encode($aCkOptions);
            $ckeditorScript = "$('#question{$oEvent->get('qid')} textarea').each(function(e){\n";
            $ckeditorScript.= "    var textarea = $(this);";
            $ckeditorScript.= "    CKEDITOR.replace( this, {$jsonCkOptions} ).on( 'change', function( event ) {\n ";
            $ckeditorScript.= "        $(textarea).val( event.editor.getData() ).trigger('keyup'); \n ";
            $ckeditorScript.= "     });";
            if(!empty($aCkOptionsLang)){
                $ckeditorScript.= "    CKEDITOR.on( 'instanceReady', function (event ){ \n";
                foreach($aCkOptionsLang as $label=>$value){
                    $ckeditorScript.= "          event.editor.lang.format.{$label}='{$value}';";
                }
                $ckeditorScript.= "     });\n";
            }
            $ckeditorScript.= "";

            $ckeditorScript.= " })\n";
            Yii::app()->clientScript->registerScript("ckeditorScript{$sAnswerId}",$ckeditorScript,CClientScript::POS_END);
        }
    }

    private function setLanguage()
    {
        // we need some replacement (for exemple de- => de)
    }
    public function saveSettings($settings)
    {
        $jsonTags=(isset($settings)) ? $settings['tags'] : '';
        $aCoreTags=array('p','pre','h1','h2','h3','h4','h5','h6','address','div');
        if($jsonTags){
            $aNewJsonTags=array();
            $aTags=json_decode($jsonTags,true);

            foreach ($aTags as $key=>$aTag)
            {
                $key=preg_replace("/[^a-zA-Z0-9]/", "", $key);
                if($key && isset($aTag['element']))
                {
                    $aNewTag=array(
                        'element'=>preg_replace("/[^a-zA-Z0-9]/", "", $aTag['element']),
                    );
                    if(!empty($aTag['attributes']) && is_array($aTag['attributes']))
                    {
                        $aNewTag['attributes']=$aTag['attributes'];
                    }
                    if(empty($aTag['description']) && !in_array($key,$aCoreTags))
                        $aNewTag['description']=$aTag['element']." ".$key;
                    elseif(!in_array($key,$aCoreTags))
                        $aNewTag['description']=$aTag['description'];
                    $aNewJsonTags[$key]=$aNewTag;
                }
            }
            $settings['tags']=json_encode($aNewJsonTags);
        }
        parent::saveSettings($settings);
    }
    public function getPluginSettings($getValues = true)
    {
        $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
        Yii::app()->clientScript->registerCssFile($assetUrl . '/settingsfix.css');
        return parent::getPluginSettings($getValues);

    }
}

