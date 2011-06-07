<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['ProxyConnectManual'] = array(
   'Name' => 'Manual Integration',
   'Description' => "This plugin allows manual configuration of ProxyConnect's various internal integration settings.",
   'Version' => '1.0.2',
   'RequiredApplications' => array('Vanilla' => '2.0.11'),
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'SettingsPermission' => 'Garden.AdminUser.Only',
   'HasLocale' => TRUE,
   'RegisterPermissions' => FALSE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com',
   'Hidden' => TRUE
);

class ProxyConnectManualPlugin extends Gdn_Plugin {
   
   public function Controller_Index($Sender) {
      
      if ($this->ProxyConnect->Provider) {
         $ConsumerKey = $Sender->ConsumerKey = GetValue('AuthenticationKey', $this->ProxyConnect->Provider, '');
         $Sender->ConsumerSecret = GetValue('AssociationSecret', $this->ProxyConnect->Provider, '');
      
         $ProviderModel = new Gdn_AuthenticationProviderModel();
         $Sender->Form->SetModel($ProviderModel);
         
         if (!$Sender->Form->AuthenticatedPostBack()) {
            $Sender->Form->SetData($this->ProxyConnect->Provider);
         } else {
            $ProviderModel->Validation->ApplyRule('URL',             'Required');
            $ProviderModel->Validation->ApplyRule('AuthenticateUrl', 'Required');
            $ProviderModel->Validation->ApplyRule('RegisterUrl',     'Required');
            $ProviderModel->Validation->ApplyRule('SignInUrl',       'Required');
            $ProviderModel->Validation->ApplyRule('SignOutUrl',      'Required');
            $Sender->Form->SetFormValue('AuthenticationKey', $ConsumerKey);
            $Sender->Form->SetFormValue('AuthenticationSchemeAlias', 'proxy');
            $Saved = $Sender->Form->Save();
         }
      }
      
      return $this->GetView('manual.php');
   }
   
   public function Controller_Cookie($Sender) {
      $ExplodedDomain = explode('.',Gdn::Request()->RequestHost());
      if (sizeof($ExplodedDomain) == 1)
         $GuessedCookieDomain = '';
      else {
         $GuessedCookieDomain = '.'.implode('.',array_slice($ExplodedDomain,-2,2));
      }
      
      $Validation = new Gdn_Validation();
      $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
      $ConfigurationModel->SetField(array('Plugin.ProxyConnect.NewCookieDomain'));
      
      // Set the model on the form.
      $Sender->Form->SetModel($ConfigurationModel);
      
      if ($Sender->Form->AuthenticatedPostBack()) {
         $NewCookieDomain = $Sender->Form->GetValue('Plugin.ProxyConnect.NewCookieDomain', '');
         SaveToConfig('Garden.Cookie.Domain', $NewCookieDomain);
      } else {
         $NewCookieDomain = $GuessedCookieDomain;
      }
      
      $Sender->SetData('GuessedCookieDomain', $GuessedCookieDomain);
      $CurrentCookieDomain = C('Garden.Cookie.Domain');
      $Sender->SetData('CurrentCookieDomain', $CurrentCookieDomain);
      
      $Sender->Form->SetData(array(
         'Plugin.ProxyConnect.NewCookieDomain'  => $NewCookieDomain
      ));
      
      $Sender->Form->SetFormValue('Plugin.ProxyConnect.NewCookieDomain', $NewCookieDomain);
      
      return $this->GetView('cookie.php');
   }
   
   public function ProxyConnectPlugin_ConfigureIntegrationManager_Handler(&$Sender) {
      $this->ProxyConnect = $Sender;
      
      // Check that we should be handling this
      if (strtolower($this->ProxyConnect->IntegrationManager) != strtolower($this->GetPluginIndex()))
         return;
      
      $this->Controller = $Sender->Controller;

      $SubController = 'Controller_'.ucfirst($Sender->SubController);
      if (!method_exists($this, $SubController))
         $SubController = 'Controller_Index';
         
      // Set view path
      $Sender->IntegrationConfigurationPath = $this->$SubController($Sender->Controller);
   }
   
   public function Setup() {
      
   }

   
}
