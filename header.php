<!-- header.php -->
<!-- 🌐 Language Switcher -->
<div class="language-switcher">
    <span class="globe-icon">&#127760;</span>
    <div id="google_translate_element"></div>
</div>

<!-- 🌟 Styles -->
<style>
.language-switcher {
    position: fixed;
    top: 12px;
    right: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #ffffffdd;
    padding: 6px 12px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    z-index: 999;
}
.language-switcher .globe-icon {
    font-size: 20px;
    cursor: default;
}
#google_translate_element select {
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 3px 6px;
    font-size: 14px;
}
.goog-te-banner-frame.skiptranslate {
    display: none !important;
}
body {
    top: 0px !important;
}
.goog-logo-link,
.goog-te-gadget span {
    display: none !important;
}
</style>

<!-- 🔧 Google Translate Script -->
<script type="text/javascript">
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'en,zh,fr,es,de,ja,ko,ar',
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE
    }, 'google_translate_element');
}
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
