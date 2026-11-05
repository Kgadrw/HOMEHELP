    </div><!-- .content -->
  </div><!-- .main -->
</div><!-- .dash-wrap -->
<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
  document.body.style.overflow = document.getElementById('sidebar').classList.contains('open') ? 'hidden' : '';
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
window.addEventListener('resize', function(){ if (window.innerWidth > 768) closeSidebar(); });
if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
