<div class="RemoteCookieNameBlock Slice" rel="dashboard/settings/proxyconnect/integration/proxyconnectmanual/remoteCookie">
   <?php echo $this->Form->Open(); ?>
   <table class="Label AltColumns">
      <thead>
         <tr>
            <th><?php echo T('Setting'); ?></th>
            <th class="Alt"><?php echo T('Value'); ?></th>
         </tr>
      </thead>
      <tbody>
         <tr class="Alt">
            <td>
               <?php echo T('Remote Cookie Name'); ?>
            </td>
            <td class="Alt">
               <?php echo $this->Form->TextBox('Plugin.ProxyConnect.RemoteCookieName'); ?>
               <?php echo $this->Form->Button("Set",array(
                        'class' => 'SliceSubmit Button'
                     ));
               ?>
            </td>
         </tr>
      </tbody>
   </table>
   <?php echo $this->Form->Close(); ?>
</div>