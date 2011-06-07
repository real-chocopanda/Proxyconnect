<div class="CookieDomainBlock Slice" rel="/settings/proxyconnect/cookie">
   <?php echo $this->Form->Open(); ?>
   <table class="Label AltColumns">
      <thead>
         <tr>
            <th><?php echo T('Setting'); ?></th>
            <th class="Alt"><?php echo T('Suggested Value'); ?></th>
         </tr>
      </thead>
      <tbody>
         <tr class="Alt">
            <?php
               $SpanClass = $this->Data['CurrentCookieDomain'] == $this->Data['GuessedCookieDomain'] ? 'Ok' : 'Warn';
            ?>
            <td>
               <?php echo T('Vanilla Cookie Domain'); ?>
               <div class="SmallerSetting">
                  <?php echo T('Currently: '); ?>
                  <span class="<?php echo $SpanClass; ?>"><?php echo $this->Data['CurrentCookieDomain']; ?></span>
               </div>
            </td>
            <td class="Alt">
               <?php echo $this->Form->TextBox('Plugin.ProxyConnect.NewCookieDomain'); ?>
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