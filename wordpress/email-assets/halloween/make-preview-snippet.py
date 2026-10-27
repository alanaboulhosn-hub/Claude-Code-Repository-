# a preview-only copy of the new bag reminder email code under its own names (no sending, no saving)
import json, sys, re
S = sys.argv[1]
src = open('fika-bag-reminder.php').read()
a = src.index("// ---------- Halloween look of the reminder ----------"); b = src.index("// ---------- storage: option fika_saved_bags")
c = src.index("	function fika_bag_email_html( $token, $r ) {"); d = src.index("	// has this email placed an order since the time given?")
code = src[a:b] + "\n" + src[c:d]
code = code.replace("if ( ! function_exists( 'fika_bag_hw' ) ) {", "if ( ! function_exists( 'fika_tmp_bag_hw' ) ) {")
for n in ['fika_bag_hw_drops', 'fika_bag_hw_img', 'fika_bag_hw', 'fika_bag_email_html']:
    code = re.sub(r'\b' + n + r'\b', 'fika_tmp_' + n[5:], code)
code = code.replace("$GLOBALS['fika_mail_hw']", "$GLOBALS['fika_tmp_mail_hw']").replace("$GLOBALS['fika_bag_hw_force']", "$GLOBALS['fika_tmp_hw_force']").replace("FIKA_BAG_HW_LIVE &&", "false &&")
code = code.replace("	function fika_tmp_bag_email_html( $token, $r ) {", "if ( ! function_exists( 'fika_tmp_bag_email_html' ) ) {\n	function fika_tmp_bag_email_html( $token, $r ) {") + "}\n"
route = open(S + '/tmp-route.php').read()
full = code + route
for bad in ['->send(', 'wp_mail', 'fika_bag_set', 'update_option']:
    assert bad not in full, bad
open(S + '/tmp-preview.php', 'w').write('<?php\n' + full)
json.dump({'name': 'TMP Claude: Halloween bag reminder preview (delete; never sends)', 'code': full, 'scope': 'global', 'active': True}, open(S + '/tmp-preview.json', 'w'))
print('ok')
