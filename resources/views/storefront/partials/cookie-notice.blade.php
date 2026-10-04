<aside id="windowshop-cookie-notice" class="windowshop-cookie-notice" role="region" aria-label="Cookie information" hidden>
    <div class="windowshop-cookie-notice-content">
        <h2>We use cookies</h2>
        <p>WindowShop uses necessary cookies to keep the site working and functional cookies to remember things like your location and login preferences.</p>
        <p class="mb-0"><a href="{{ route('storefront.cookie-policy') }}">Cookie Policy</a> <span aria-hidden="true">·</span> <a href="{{ route('storefront.privacy') }}">Privacy Policy</a></p>
    </div>
    <button type="button" class="btn btn-primary" data-cookie-dismiss>Got it</button>
</aside>
<style>
.windowshop-cookie-notice{position:fixed;z-index:1080;left:1rem;right:1rem;bottom:1rem;max-width:760px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1.25rem;background:#fff;border:1px solid rgba(0,0,0,.12);border-radius:.75rem;box-shadow:0 .5rem 1.5rem rgba(0,0,0,.16)}
.windowshop-cookie-notice-content{flex:1 1 auto;min-width:0}.windowshop-cookie-notice [data-cookie-dismiss]{flex:0 0 auto;min-width:max-content;white-space:nowrap;padding-left:1.25rem;padding-right:1.25rem}
.windowshop-cookie-notice h2{font-size:1rem;margin:0 0 .35rem}.windowshop-cookie-notice p{font-size:.875rem;margin:0 0 .35rem}.windowshop-cookie-notice a{color:inherit;text-decoration:underline}
@media(max-width:575.98px){.windowshop-cookie-notice{align-items:stretch;flex-direction:column;bottom:.5rem;left:.5rem;right:.5rem;padding:.9rem}.windowshop-cookie-notice [data-cookie-dismiss]{width:100%;min-height:2.75rem}}
</style>
<script>
(() => { const name='windowshop_cookie_notice_v1', notice=document.getElementById('windowshop-cookie-notice'); if(!notice)return; const has=document.cookie.split('; ').some(c=>c.startsWith(name+'=')); const show=()=>{notice.hidden=false}; if(!has)show(); document.querySelector('[data-cookie-dismiss]')?.addEventListener('click',()=>{document.cookie=name+'=dismissed; Max-Age=31536000; Path=/; SameSite=Lax'; notice.hidden=true}); document.querySelector('[data-cookie-settings]')?.addEventListener('click',()=>{show(); notice.scrollIntoView({block:'nearest'});}); })();
</script>
