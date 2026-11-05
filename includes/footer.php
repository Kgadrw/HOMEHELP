<footer class="hh-page-footer">
  <p>
    &copy; <?= date('Y') ?> Home Help &middot; Rwanda
  </p>
</footer>
<style>
.hh-page-footer{
  text-align:center;
  padding:18px 20px;
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:0.78rem;
  color:rgba(255,255,255,0.45);
  background:var(--navy-deep,#0D1B4B);
  border-top:1px solid rgba(255,255,255,0.07);
  margin-top:auto;
}
.hh-page-footer p{margin:0}
.hh-page-footer a{
  color:rgba(255,255,255,0.6);
  text-decoration:none;
  transition:color 0.2s;
}
.hh-page-footer a:hover{color:#E8A020}
/* On light-bg pages (client/agent dashboards) */
.page-light .hh-page-footer{
  background:#F2EDE3;
  color:#888;
  border-top:1px solid #e0dbd2;
}
.page-light .hh-page-footer a{color:#2E5FC2}
.page-light .hh-page-footer a:hover{color:#E8A020}
</style>