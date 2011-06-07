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
$PluginInfo['ProxyConnectWordpress'] = array(
   'Name' => 'Wordpress Integration',
   'Description' => 'This plugin tightens the integration between Wordpress and Vanilla when ProxyConnect is enabled.',
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

class ProxyConnectWordpressPlugin extends Gdn_Plugin {
   
   public function Controller_Index($Sender) {
      $this->AddSliceAsset($this->GetResource('css/wordpress.css', FALSE, FALSE));

      $ProviderModel = new Gdn_AuthenticationProviderModel();
      
      $LastState = NULL; $State = NULL;
      do {
         $LastState = $State;
         $State = $this->State($this->ProxyConnect->Provider);
         $Sender->SetData('IntegrationState', $State);
         
         switch ($State) {
            case 'Address':
               // Gather remote address from user.
               
               // User has submitted the form and provided a URL
               if ($Sender->Form->AuthenticatedPostBack()) {
                  
                  // Address supplied. Query the remote blog.
                  $Address = $Sender->Form->GetValue('WordpressUrl', NULL);
                  if (empty($Address)) {
                     $Sender->Form->AddError("Please enter the URL for your blog.");
                     return $this->GetView('wordpress.php');
                  }
                  
                  $this->ProxyConnect->Provider['URL'] = $Address;
                  $Response = $this->QueryRemote($this->ProxyConnect->Provider, 'Check', NULL, FALSE);
                  
                  if (GetValue('X-ProxyConnect-Enabled', $Response) == 'yes') {
                     // Proxyconnect is enabled at the provided URL.
                     $ProviderModel->Save($this->ProxyConnect->Provider);
                  } else {
                     unset ($this->ProxyConnect->Provider['URL']);
                     $Sender->Form->AddError("Unable to contact remote plugin. Perhaps your blog URL was incorrect?");
                     return $this->GetView('wordpress.php');
                  }
               } else {
                  
                  // break out of the loop and let the form render
                  break 2;
               }
               
            break;
            
            case 'Exchange':
               if ($LastState == $State) {
                  // exchanging again. 
                  $Sender->Form->AddError("Unable to poll remote plugin for all required information. Switch to manual integration.");
                  return $this->GetView('wordpress.php');
               }
               // 1: push challenge key to remote.
               // 2: gather urls 
               // 3: set cookie domain
               
               // 1 - push challenge key
               $Response = $this->QueryRemote($this->ProxyConnect->Provider, 'Secure', array(
                  'Challenge' => GetValue('AssociationSecret', $this->ProxyConnect->Provider, 'flam')
               ));
               
               $ChallengeSet = GetValue('X-Autoconfigure-Challenge', $Response);
               if ($ChallengeSet != 'set') {
                  $Sender->Form->AddError("Could not set Challenge key on remote. Reason was: challenge {$ChallengeSet}");
                  return $this->GetView('wordpress.php');
               }
               
               // 2 - gather URLs
               $Response = $this->QueryRemote($this->ProxyConnect->Provider, 'Exchange', NULL, TRUE, TRUE);
               $Result = json_decode($Response);
               
               $CheckURLs = array(
                  'AuthenticateUrl',
                  'RegisterUrl',
                  'SignInUrl',
                  'SignOutUrl',
                  'PasswordUrl',
                  'ProfileUrl'
               );
               
               foreach ($CheckURLs as $CheckURL) {
                  $Value = GetValue($CheckURL, $Result, NULL);
                  if (!is_null($Value)) {
                     $this->ProxyConnect->Provider[$CheckURL] = $Value;
                  }
               }
               
               // save the provider data
               $ProviderModel->Save($this->ProxyConnect->Provider);
               
               // 3 - set cookie domain
               $ExplodedDomain = explode('.',Gdn::Request()->RequestHost());
               if (sizeof($ExplodedDomain) == 1)
                  $GuessedCookieDomain = '';
               else
                  $GuessedCookieDomain = '.'.implode('.',array_slice($ExplodedDomain,-2,2));
               
               $Response = $this->QueryRemote($this->ProxyConnect->Provider, 'Cookie', array(
                  'CookieDomain' => $GuessedCookieDomain
               ), TRUE, TRUE);
               if (GetValue('X-Autoconfigure-Cookie', $Response, NULL) == 'set') {
                  // Set local cookie domain too
                  SaveToConfig('Garden.Cookie.Domain', $GuessedCookieDomain);
               }
               
            break;
            
            case NULL:
               // provider is fully configured.
               $Sender->SetData('BlogURL', GetValue('URL', $this->ProxyConnect->Provider));
            break;
            
            case 'Error':
               return $this->GetView('providerfailed.php');
            break;
         }
      } while (!is_null($State));
      
      return $this->GetView('wordpress.php');
   }
   
   protected function QueryRemote($Provider, $Task, $Arguments = NULL, $Secure = TRUE, $GetBody = FALSE) {
      if (!is_array($Arguments)) $Arguments = array();
      
      $Arguments = array_merge($Arguments, array(
         'ProxyConnectAutoconfigure'   => 'configure',
         'Task'                        => $Task
      ));
      
      if ($Secure) {
         $Arguments = array_merge($Arguments, array(
            'Key'                      => GetValue('AssociationSecret', $Provider)
         ));
      }
      
      $RealURL = GetValue('URL', $Provider)."?".http_build_query($Arguments);      
      if ($GetBody)
         return ProxyRequest($RealURL, FALSE, TRUE);
      else
         return ProxyHead($RealURL, NULL, FALSE, TRUE);
   }
   
   protected function State($Provider = NULL) {
      if (is_null($Provider))
         return 'Error';
         
      if (GetValue('URL', $Provider) == NULL) {
         return 'Address';
      }
         
      if (
         GetValue('AuthenticateUrl', $Provider) == NULL ||
         GetValue('RegisterUrl', $Provider) == NULL ||
         GetValue('SignInUrl', $Provider) == NULL ||
         GetValue('SignOutUrl', $Provider) == NULL
      ) {
         return 'Exchange';
      }
            
      return NULL;
   }
   
   public function ProxyConnectPlugin_ConfigureIntegrationManager_Handler(&$Sender) {
      $this->ProxyConnect = $Sender;
            
      // Check that we should be handling this
      if (strtolower($this->ProxyConnect->IntegrationManager) != strtolower($this->GetPluginIndex()))
         return;
      
      $this->Controller = $Sender->Controller;
      $this->EnableSlicing($Sender->Controller);

      $SubController = 'Controller_'.ucfirst($Sender->SubController);
      if (!method_exists($this, $SubController))
         $SubController = 'Controller_Index';
         
      // Set view path
      $Sender->IntegrationConfigurationPath = $this->$SubController($Sender->Controller);
      $Sender->Controller->SliceConfig = $this->RenderSliceConfig();
   }
   
   public function Setup() {
      
   }
   
}