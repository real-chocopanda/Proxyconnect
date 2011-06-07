<div class="IntegrationManagerConfigure Slice" rel="dashboard/settings/proxyconnect/integration/proxyconnectwordpress">
   <div class="SliceConfig"><?php echo $this->SliceConfig; ?></div>
   <div class="ProxyConnectWordpress">
      <p>Unable to create wordpress proxyconnect provider entry in GDN_UserAuthenticationProvider.</p>
      
      <p><b>Possible reasons</b>:</p>
      <ul>
         <li>Database was not created. Did you enable ProxyConnect properly, through the Plugins screen?</li>
         <li>Does your database user have write permissions to the GDN_UserAuthentication* tables?</li>
      </ul>
   </div>
</div>