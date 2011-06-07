<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

/**
 * Validates sessions by handshaking with another site by means of direct socket connection
 * 
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package Garden
 */
class Gdn_ProxyAuthenticator extends Gdn_Authenticator implements Gdn_IHandshake {

   public $HandshakeResponse = NULL;
   protected $_CookieName = NULL;
   protected $Provider = NULL;
   protected $Token = NULL;
   protected $Nonce = NULL;
   
   public function __construct() {
   
      // This authenticator gets its data directly from the request object, always
      $this->_DataSourceType = Gdn_Authenticator::DATA_NONE;
      
      // Which cookie signals the presence of an authentication package?
      $this->_CookieName = Gdn::Config('Garden.Authenticators.proxy.CookieName', 'VanillaProxy');
      
      // Initialize built-in authenticator functionality
      parent::__construct();
   }
   
   public function Authenticate() {
      try {
         $Provider = $this->GetProvider();
         if (!$Provider) throw new Exception('Provider not defined');
      
         $ForeignIdentityUrl = GetValue('AuthenticateUrl', $Provider, FALSE);
         if ($ForeignIdentityUrl === FALSE) throw new Exception('AuthenticateUrl not defined');
      
         $Response = $this->_GetForeignCredentials($ForeignIdentityUrl);
         if (!$Response) throw new Exception('No response from Authentication URL');
         $this->HandshakeResponse = $Response;
         
         // @TODO: Response sends provider key, used as parameter to GetProvider()

         // Got a response from the remote identity provider, and loaded matching provider
         $AuthUniqueField = C('Garden.Authenticators.proxy.AuthField','UniqueID');
         $UserUnique = GetValue($AuthUniqueField, $Response, NULL);
         if (is_null($UserUnique))
            throw new Exception("Selected AuthUniqueField ({$AuthUniqueField}) not found in Response.");
         
         $UserEmail = GetValue('Email', $Response);
         $UserName = GetValue('Name', $Response);
         $UserName = trim(preg_replace('/[^a-z0-9- ]+/i','',$UserName));
         $TransientKey = GetValue('TransientKey', $Response, NULL);
         $Roles = GetValue('Roles', $Response, NULL);
         
         // Validate remote credentials against local auth tables
         $AuthResponse = $this->ProcessAuthorizedRequest($Provider['AuthenticationKey'], $UserUnique, $UserName, $TransientKey, array(
            'Email'  => $UserEmail,
            'Roles' => $Roles
         ));
         
         return $AuthResponse;
      } catch (Exception $e) {

         // Fallback to defer checking until the next session
         if (substr(Gdn::Request()->Path(),0,6) != 'entry/')
            $this->SetIdentity(-1, FALSE);

         // Error of some kind. Very sad :(
         return Gdn_Authenticator::AUTH_ABORTED;
      }
   }
   
   public function Finalize($UserKey, $UserID, $ProviderKey, $TokenKey, $CookiePayload) {
      // Associate the local UserID with the foreign UserKey
      Gdn::Authenticator()->AssociateUser($ProviderKey, $UserKey,  $UserID);
      
      // Log the user in
      $this->ProcessAuthorizedRequest($ProviderKey, $UserKey);
   }
   
   /**
    * 
    * 
    * 
    * 
    * 
    */
   public function ProcessAuthorizedRequest($ProviderKey, $UserKey, $UserName = NULL, $ForeignNonce = NULL, $OptionalPayload = NULL) {

      // Try to load the association for this Provider + UserKey
      $Association = Gdn::Authenticator()->GetAssociation($UserKey, $ProviderKey, Gdn_Authenticator::KEY_TYPE_PROVIDER);
      
      // We havent created a UserAuthentication entry yet. Create one. This will be an un-associated entry.
      if (!$Association) {
         $Association = Gdn::Authenticator()->AssociateUser($ProviderKey, $UserKey, 0);
         
         // Couldn't even create a half-association.
         if (!$Association) 
            return Gdn_Authenticator::AUTH_DENIED;
      }
      
      // Retrieved an association which has been fully linked to a local user
      if ($Association['UserID'] > 0) {
      
         // We'll be tracked by Vanilla cookies now, so delete the Proxy cookie if it exists...
         $this->DeleteCookie();
         
         // Log the user in.
         $this->SetIdentity($Association['UserID'], FALSE);
         
         // Check for a request token that needs to be converted to an access token
         $Token = $this->LookupToken($ProviderKey, $UserKey, 'request');
         
         if ($Token) {
            // Check for a stored Nonce
            $ExistingNonce = $this->LookupNonce($Token['Token']);
            
            // Found one. Copy it as if it was passed in to this method, and then delete it.
            if ($ExistingNonce !== FALSE) {
               $ForeignNonce = $ExistingNonce;
               $this->ClearNonces($Token['Token']);
            }
               
            unset($Token);
         }

         // Sync the user's email and roles.
         if (is_array($OptionalPayload) && count($OptionalPayload) > 0) {
            if (isset($OptionalPayload['Email'])) {
               Gdn::SQL()->Put('User', array('Email' => $OptionalPayload['Email']), array('UserID' => $Association['UserID']));
            }
            $Roles = GetValue('Roles', $OptionalPayload, FALSE);
            if ($Roles) {
               Gdn::UserModel()->SaveRoles($Association['UserID'], $Roles, FALSE);
               Gdn::Session()->Start($Association['UserID'], TRUE);
            }
         }
         
         $TokenType = 'access';
         $AuthReturn = Gdn_Authenticator::AUTH_SUCCESS;
      } else {
         // This association is not yet associated with a local forum account. 
         
         // Set the memory cookie to trigger the handshake page
         $CookiePayload = array(
            'UserKey'      => $UserKey,
            'ProviderKey'  => $ProviderKey,
            'UserName'     => $UserName,
            'UserOptional' => Gdn_Format::Serialize($OptionalPayload)
         );
         $SerializedCookiePayload = Gdn_Format::Serialize($CookiePayload);
         $this->Remember($ProviderKey, $SerializedCookiePayload);
         
         $TokenType = 'request';
         $AuthReturn = Gdn_Authenticator::AUTH_PARTIAL;
      }
      
      $Token = $this->LookupToken($ProviderKey, $UserKey, $TokenType);
      if (!$Token)
         $Token = $this->CreateToken($TokenType, $ProviderKey, $UserKey, TRUE);
      
      if ($Token && !is_null($ForeignNonce)) {
         $TokenKey = $Token['Token'];
         try {
            $this->SetNonce($TokenKey, $ForeignNonce);
         } catch (Exception $e) {}
      }
      
      return $AuthReturn;
   }
   
   public function Remember($Key, $SerializedCookiePayload) {
      $this->SetCookie($Key, $SerializedCookiePayload, 0);
   }
   
   public function GetHandshake() {
      $HaveHandshake = $this->CheckCookie();
      
      if ($HaveHandshake) {
         // Found a handshake cookie, sweet. Get the payload.
         $Payload = $this->GetCookie();
         
         // Rebuild the real payload
         $ReconstitutedCookiePayload = Gdn_Format::Unserialize(TrueStripSlashes(array_shift($Payload)));
         
         return $ReconstitutedCookiePayload;
      }
      
      return FALSE;
   }

   public function SetCookie($Key, $Payload) {
      $Path = Gdn::Config('Garden.Cookie.Path', '/');
      $Domain = Gdn::Config('Garden.Cookie.Domain', '');

      // If the domain being set is completely incompatible with the current domain then make the domain work.
      $CurrentHost = Gdn::Request()->Host();
      if (!StringEndsWith($CurrentHost, trim($Domain, '.')))
         $Domain = '';

      $CookieHashMethod = C('Garden.Cookie.HashMethod');
      $CookieSalt = C('Garden.Cookie.Salt');

      // Create the cookie contents
      $KeyHash = self::_Hash($Key, $CookieHashMethod, $CookieSalt);
      $Hash = self::_HashHMAC($CookieHashMethod, $Key, $KeyHash);
      $Cookie = array($Key,$Hash,time());
      if (!is_null($Payload)) {
         if (!is_array($Payload))
            $Payload = array($Payload);
         $Cookie = array_merge($Cookie, $Payload);
      }

      $CookieContents = implode('|',$Cookie);

      // Create the cookie. Lasts for the browser session only.
      setcookie($this->_CookieName, $CookieContents, 0, $Path, $Domain);
      $_COOKIE[$this->_CookieName] = $CookieContents;
   }

   public function GetCookie() {
      if (!$this->CheckCookie($this->_CookieName)) return FALSE;

      $Payload = explode('|', $_COOKIE[$this->_CookieName]);

      array_shift($Payload);
      array_shift($Payload);
      array_shift($Payload);
      
      return $Payload;
   }

   public function CheckCookie() {
      if (empty($_COOKIE[$this->_CookieName]))
         return FALSE;

      $CookieHashMethod = C('Garden.Cookie.HashMethod');
      $CookieSalt = C('Garden.Cookie.Salt');

      $CookieData = explode('|', $_COOKIE[$this->_CookieName]);
      if (count($CookieData) < 4) {
         $this->DeleteCookie();
         return FALSE;
      }

      list($Key, $CookieHash, $Time, $CookiePayload) = $CookieData;

      $KeyHash = self::_Hash($Key, $CookieHashMethod, $CookieSalt);
      $GeneratedHash = self::_HashHMAC($CookieHashMethod, $Key, $KeyHash);

      if (!CompareHashDigest($CookieHash, $GeneratedHash))
         return $this->DeleteCookie();
      
      return TRUE;
   }
   
   public function DeleteCookie() {
      $Path = C('Garden.Cookie.Path');
      $Domain = C('Garden.Cookie.Domain');

      $Expiry = strtotime('one year ago');
      setcookie($this->_CookieName, "", $Expiry, $Path, $Domain);
      $_COOKIE[$this->_CookieName] = NULL;

      return TRUE;
   }

   /**
    * Returns $this->_HashHMAC with the provided data, the default hashing method
    * (md5), and the server's COOKIE.SALT string as the key.
    *
    * @param string $Data The data to place in the hash.
    */
   protected static function _Hash($Data, $CookieHashMethod, $CookieSalt) {
      return self::_HashHMAC($CookieHashMethod, $Data, $CookieSalt);
   }

   /**
    * Returns the provided data hashed with the specified method using the
    * specified key.
    *
    * @param string $HashMethod The hashing method to use on $Data. Options are MD5 or SHA1.
    * @param string $Data The data to place in the hash.
    * @param string $Key The key to use when hashing the data.
    */
   protected static function _HashHMAC($HashMethod, $Data, $Key) {
      $PackFormats = array('md5' => 'H32', 'sha1' => 'H40');

      if (!isset($PackFormats[$HashMethod]))
         return false;

      $PackFormat = $PackFormats[$HashMethod];
      // this is the equivalent of "strlen($Key) > 64":
      if (isset($Key[63]))
         $Key = pack($PackFormat, $HashMethod($Key));
      else
         $Key = str_pad($Key, 64, chr(0));

      $InnerPad = (substr($Key, 0, 64) ^ str_repeat(chr(0x36), 64));
      $OuterPad = (substr($Key, 0, 64) ^ str_repeat(chr(0x5C), 64));

      return $HashMethod($OuterPad . pack($PackFormat, $HashMethod($InnerPad . $Data)));
   }
   
   public function GetUserKeyFromHandshake($Handshake) {
      return ArrayValue('UserKey', $Handshake, FALSE);
   }
   
   public function GetUserNameFromHandshake($Handshake) {
      return ArrayValue('UserName', $Handshake, FALSE);
   }
   
   public function GetProviderKeyFromHandshake($Handshake) {
      return ArrayValue('ProviderKey', $Handshake, FALSE);
   }
   
   public function GetTokenKeyFromHandshake($Handshake) {
      return '';  // this authenticator doesnt use tokens
   }
   
   public function GetUserEmailFromHandshake($Handshake) {
      static $UserOptional = NULL;
      
      if (is_null($UserOptional)) {
         $UserOptional = Gdn_Format::Unserialize(ArrayValue('UserOptional', $Handshake, array()));
      }
      return ArrayValue('Email', $UserOptional, '');
   }

   public function GetRolesFromHandshake($Handshake) {
      static $UserOptional = NULL;

      if (is_null($UserOptional)) {
         $UserOptional = Gdn_Format::Unserialize(ArrayValue('UserOptional', $Handshake, array()));
      }
      return ArrayValue('Roles', $UserOptional, '');
   }
   
   public function DeAuthenticate() {
      $this->SetIdentity(-1, FALSE);
      return Gdn_Authenticator::AUTH_SUCCESS;
   }
   
   // What to do if entry/auth/* is called while the user is logged out. Should normally be REACT_RENDER
   public function LoginResponse() {
      return Gdn::Authenticator()->RemoteSignInUrl();
   }
   
   // What to do after part 1 of a 2 part authentication process. This is used in conjunction with OAauth/OpenID type authentication schemes
   public function PartialResponse() {
      return Gdn_Authenticator::REACT_REDIRECT;
   }
   
   // What to do after authentication has succeeded. 
   public function SuccessResponse() {
      return Gdn_Authenticator::REACT_REDIRECT;
   }
   
   // What to do if the entry/auth/* page is triggered for a user that is already logged in
   public function RepeatResponse() {
      return Gdn_Authenticator::REACT_REDIRECT;
   }
   
   // What to do if the entry/leave/* page is triggered for a user that is logged in and successfully logs out
   public function LogoutResponse() {
      return Gdn::Authenticator()->RemoteSignOutUrl();
   }
   
   // What to do if the entry/auth/* page is triggered but login is denied or fails
   public function FailedResponse() {
      return Gdn_Authenticator::REACT_RENDER;
   }
   
   public function GetHandshakeMode() {
      $ModeStr = Gdn::Request()->GetValue('mode', Gdn_Authenticator::HANDSHAKE_DIRECT);
      return $ModeStr;
   }
   
   public function GetURL($URLType) {
      $Provider = $this->GetProvider();
      $Nonce = $this->GetNonce();
      
      // Dirty hack to allow handling Remote* url requests and delegate basic requests to the config
      if (strlen($URLType) == strlen(str_replace('Remote','',$URLType))) return FALSE;
      
      $URLType = str_replace('Remote','',$URLType);
      // If we get here, we're handling a RemoteURL question
      if ($Provider && GetValue($URLType, $Provider, FALSE)) {
         return array(
            'URL'          => $Provider[$URLType],
            'Parameters'   => array(
               'Nonce'  => $Nonce['Nonce']
            )
         );
      }
      
      return FALSE;
   }
   
   protected function _GetForeignCredentials($ForeignIdentityUrl) {
   
      // Get the contents of the Authentication Url (timeout 5 seconds);
      @session_write_close();
      $Response = ProxyRequest($ForeignIdentityUrl, 5);
      
      if ($Response) {
      
         $ReadMode = strtolower(C("Garden.Authenticators.proxy.RemoteFormat", "ini"));
         switch ($ReadMode) {
            case 'ini':
               $Result = @parse_ini_string($Response);
               break;
               
            case 'json':
               $Result = @json_decode($Response);
               break;
               
            default:
               throw new Exception("Unexpected value '$ReadMode' for 'Garden.Authenticators.proxy.RemoteFormat'");
         }
         
         if ($Result) {
            $ReturnArray = array(
               'Email'        => ArrayValue('Email', $Result),
               'Name'         => ArrayValue('Name', $Result),
               'UniqueID'     => ArrayValue('UniqueID', $Result),
               'TransientKey' => ArrayValue('TransientKey', $Result, NULL)
            );

            if (isset($Result['Roles']))
               $ReturnArray['Roles'] = $Result['Roles'];

            return $ReturnArray;
         }
      }
      return FALSE;
   }
   
   public function CurrentStep() {
      $Id = Gdn::Authenticator()->GetRealIdentity();
      
      if (!$Id) return Gdn_Authenticator::MODE_GATHER;
      if ($Id > 0) return Gdn_Authenticator::MODE_REPEAT;
      if ($Id < 0) return Gdn_Authenticator::MODE_NOAUTH;
   }
   
   public function AuthenticatorConfiguration(&$Sender) {
      // Let the plugin handle the config
      $Sender->AuthenticatorConfigure = NULL;
      $Sender->FireEvent('AuthenticatorConfigurationProxy');
      return $Sender->AuthenticatorConfigure;
   }
   
   public function WakeUp() {
      
      // Allow the entry/handshake method to function
      Gdn::Authenticator()->AllowHandshake();
      
      if (Gdn::Request()->Path() == 'entry/auth/proxy') return;
      if (Gdn::Request()->Path() == 'entry/handshake/proxy') return;
      // Shortcircuit the wakeup if we're already awake
      // 
      // If we're already back on Vanilla and working with the handshake form, don't
      // try to re-wakeup.
      $HaveHandshake = $this->CheckCookie();
      if ($HaveHandshake)
         return;

      $CurrentStep = $this->CurrentStep();

      // Shortcircuit to prevent pointless work when the access token has already been handled and we already have a session 
      if ($CurrentStep == Gdn_Authenticator::MODE_REPEAT)
         return;
         
      // Don't try to wakeup when we've already tried once this session
      if ($CurrentStep == Gdn_Authenticator::MODE_NOAUTH)
         return;
      
      try {
      
         // Passed all shortcircuits. Try to log in via proxy.
         $AuthResponse = $this->Authenticate();

         $UserInfo = array();
         $UserEventData = array_merge(array(
            'UserID'    => Gdn::Session()->UserID,
            'Payload'   => GetValue('HandshakeResponse', $this, FALSE)
         ),$UserInfo);
         Gdn::Authenticator()->Trigger($AuthResponse,$UserEventData);

         if ($AuthResponse == Gdn_Authenticator::AUTH_PARTIAL) {
            return Redirect(Url('/entry/handshake/proxy',TRUE),302);
         }
         
      } catch (Exception $e) {
         
         // Fallback to defer checking until the next session
         if (substr(Gdn::Request()->Path(),0,6) != 'entry/')
            $this->SetIdentity(-1, FALSE);
      }
   }
   
}