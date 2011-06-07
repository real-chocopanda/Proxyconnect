<table class="SplitConfiguration">
   <thead>
      <th><?php echo T('Vanilla Configuration'); ?></th>
      <th><?php echo T('Remote Configuration'); ?></th>
   </thead>
   <tbody>
      <td class="VanillaConfig">
         <?php
            echo $this->Form->Open();
            echo $this->Form->Errors();
            echo $this->Form->Hidden('AuthenticationKey', $this->ConsumerKey);
         ?>
         <div>
            <div class="Box HighlightBox"><?php echo T("If you are using ProxyConnect with an officially supported remote application plugin such as our wordpress-proxyconnect plugin, these values will be available in that plugin's configuration screen."); ?></div>
         </div>
         <ul>
            <li><?php
               echo $this->Form->Label(T('Main Site URL'), 'Url');
               echo $this->Form->TextBox('URL');
               echo Wrap(T('The URL of your website where you will use ProxyConnect'));
            ?></li>
            <li><?php
               echo $this->Form->Label(T('Authenticate URL'), 'AuthenticateURL');
               echo $this->Form->TextBox('AuthenticateURL');
               echo Wrap(T('The behind-the-scenes URL that shares identity information with Vanilla'));
            ?></li>
            <li><?php
               echo $this->Form->Label(T('Registration URL'), 'RegisterUrl');
               echo $this->Form->TextBox('RegisterUrl');
               echo Wrap(T('The URL where users can sign up for new accounts on your site'));
            ?></li>
            <li><?php
               echo $this->Form->Label(T('Sign-In URL'), 'SignInUrl');
               echo $this->Form->TextBox('SignInUrl');
               echo Wrap(T('The URL where users sign in on your site'));
            ?></li>
            <li><?php
               echo $this->Form->Label(T('Sign-Out URL'), 'SignOutUrl');
               echo $this->Form->TextBox('SignOutUrl');
               echo Wrap(T('The URL where users sign out of your site'));
            ?></li>
         </ul>
         <?php
            echo $this->Form->Close('Save', '', array(
                              'class' => 'SliceSubmit Button'
                           ));
         ?>
      </td>
      
      <td class="RemoteConfig">
         <div>
            <?php echo T("These are the settings you might need when you configure ProxyConnect on your remote website."); ?>
            <p>
               <?php echo T("You will probably need to configure Vanilla and your remote application to use a shared Cookie Domain that they can both access. We've
               tried to guess what that might be, based on your hostname, but you'll need to check this and make sure that it works."); ?>
            </p><br/>
         </div>
         <?php 
            echo Gdn::Slice('dashboard/settings/proxyconnect/cookie');
         ?>
      </td>
   </tbody>
</table>