﻿(function(){CKEDITOR.plugins.add("ipspage",{icons:"ipspage",init:function(a){a.addCommand("ipspage",ips.utils.defaultEditorPlugins.block("ipspage","div",{"data-role":"contentPage"},"",!0,"\x3chr data-role\x3d'contentPageBreak'\x3e"));a.ui.addButton&&a.ui.addButton("ipspage",{label:ips.getString("editorbutton_ipspage"),command:"ipspage",toolbar:""})}})})();