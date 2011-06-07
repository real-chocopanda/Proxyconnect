<div class="IntegrationManagerConfigure Slice" rel="dashboard/settings/proxyconnect/integration/proxyconnectwordpress">
   <div class="SliceConfig"><?php echo $this->SliceConfig; ?></div>
   <div class="ProxyConnectWordpress">
   <?php
      echo $this->Form->Open();
      echo $this->Form->Errors();
   
      $IntegrationState = $this->Data('IntegrationState');
   
      $SaveButton = FALSE;
      echo "<div class=\"Task {$IntegrationState}\">\n";
      switch ($IntegrationState) {
         case 'Address':
            echo Wrap(T("Enter the address of your Wordpress Blog and we'll take it from there."), 'p',array('class' => 'WordpressStep'));
            echo $this->Form->TextBox('WordpressUrl');
            $SaveButton = TRUE;
         break;
         
         case 'Exchange':
            echo Wrap(T("The Wordpress Remote Integration Manager has been unable to automatically configure your ProxyConnect system. Please choose 'Manual' Integration above and perform a manual configuration."), 'p',array('class' => 'WordpressStep'));
            $SaveButton = FALSE;
         break;
         
         default:
            echo Wrap(sprintf(T("ProxyConnect has been configured to work with your blog, <b>%s</b>. Now would be a good time to Activate the authenticator and do a little testing."),$this->Data('BlogURL')), 'p',array('class' => 'WordpressStep'));
            $SaveButton = FALSE;
         break;
      }
      
      echo "</div>\n";
      
      if ($SaveButton) {
         echo $this->Form->Close('Configure', '', array(
            'class' => 'SliceSubmit Button'
         ));
      } else {
         echo $this->Form->Close();
      }
   ?>
   </div>
</div>