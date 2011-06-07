<?php if (!defined('APPLICATION')) exit(); ?>
<div class="AuthenticationConfigure Slice" rel="dashboard/settings/proxyconnect">
   <div class="SliceConfig"><?php echo $this->SliceConfig; ?></div>
   
   <h3><?php echo T('Proxy Connect'); ?></h3>
   <div class="Info">
      <div class="ProxyConnectInfo">
         <?php echo T('This authenticator allows users from your remote application or website to be automatically registered and signed into Vanilla. For a detailed explanation of how Proxy Connect works, please <a href="http://vanillaforums.org/page/singlesignon">read our documentation</a>.'); ?>
         <?php echo T('Proxy Connect ships with several pre-built <b>Remote Integration Managers</b>, each designed to automate the setup process. If your remote application is listed in the dropdown below, select it now, otherwise choose "Manual Setup".'); ?>
         <div class="IntegrationChooser">
            <?php
               echo $this->Form->Open(array(
                  'action'  => Url('dashboard/authentication/choose')
               ));
               echo $this->Form->Errors();

               echo $this->Form->Label("Integration Manager: ");
               echo $this->Form->DropDown('Garden.Authentication.IntegrationChooser', array_merge($this->Data('IntegrationChooserList')), array(
                  'value'  => $this->Data('PreFocusIntegration'),
                  'class'  => 'IntegrationChooser',
                  'disabled'  => 'disabled'
               ));

               echo $this->Form->Close();
            ?>
         </div>
      </div>
   </div>
   
   <script type="text/javascript">
      var IntegrationList = <?php echo json_encode($this->Data('IntegrationChooserList')); ?>;
      jQuery(document).one('SliceReady', function(Event) {
         $('select.IntegrationChooser').attr('disabled', false);
         if ($('select.IntegrationChooser').attr('bound')) return;
         
         var ChosenIntegrationManager = '<?php echo $this->Data('PreFocusIntegration'); ?>';
         if (!ChosenIntegrationManager) {
            $('select.IntegrationChooser').val('');
         }
   
         $('select.IntegrationChooser').attr('bound',true);
         $('select.IntegrationChooser').bind('change',function(e){
            var Chooser = $(e.target);
            var SliceElement = $('div.IntegrationManagerConfigure');
            var SliceObj = SliceElement.attr('Slice');
            
            var ChooserVal = Chooser.val();
            var ChosenURL = (ConfigureList[ChooserVal]) ? ConfigureList[ChooserVal] : ((ConfigureList[ChooserVal] != 'undefined') ? '/dashboard/settings/proxyconnect/integrate/'+ChooserVal : false);
            if (ChosenURL)
               SliceObj.ReplaceSlice(ChosenURL);
         });
      });
   </script>
   
   <?php
      $IntegrationSuffix = (!$this->Data('PreFocusIntegration')) ? NULL : "/".$this->Data('PreFocusIntegration');
   ?>
   <div class="IntegrationManagerConfigure Slice Async" rel="dashboard/settings/proxyconnect/integration"></div>
</div>