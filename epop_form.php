 <html>
 <head>
 	<title>
 		
</title>
<style>
 			<!--
 			.submitbtn {margin-top:8px;margin-left:100px;}
 			.title {display:block;float:left;width:100px;}
 			-->
 		</style>
 </head>
 <body>
 <h3>Özeps</h3>
<form id="login" action="http://www.oezeps.at/moodle/blocks/exaport/epopal.php?url=/course/view.php?id=4" method="post">

der Pfad: <input type="text" size="100" value="/mod/questionnaire/view.php?id=18" name="url_temp"><br>


<input type="submit" value="zu oezeps verbinden">

</form>

 	<h3>login</h3>
 	<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" >
 		<br><span class="title">username:</span> <input type="text" name="username" value="dietmar"> (id=7)
 		<br><span class="title">passwort: </span><input type="password" name="password" value="<?php echo md5("Hansi123!")?>">
 		<br><span class="title">action: </span><input type="text" name="action" value="login">
 		<br><input type="submit" value="Authentifizieren" class="submitbtn">
 	</form>
 	<b>babsi key: </b>3a720de5ac0a58ecf7b2f1e48169b
 		<h3>upload</h3>
 		
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">title: </span><input type="text" name="title" value="Mein cooles Artefakt">
 		<br><span class="title">catid: </span><input type="text" name="catid" value="154">
 		<br><span class="title">itemid (zum update):</span> <input type="text" name="itemid" value="154">
 		<br><span class="title">description:</span> <input type="text" name="description" value="und a bisserl a beschreibung dazua">
 		<br><span class="title">url: </span><input type="text" name="url" value="www.gtn-solutions.com">
 		<br><span class="title">file: </span><input type="file" name="datei">
 		<br><span class="title">action: </span><input type="text" name="action" value="upload">
 		<br><span class="title">competences: </span><input type="text" name="competences" value="59_60_61_">
 		<br><input type="submit" value="Dateien hochladen" class="submitbtn">
</form>

<h3>get upload id</h3>
 		
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action: </span><input type="text" name="action" value="get_lastitemID">
 		<br><input type="submit" value="Upload id holen" class="submitbtn">
</form>

<h3>child_categories</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">catid: </span><input type="text" name="catid" value="52">
 		<br><span class="title">action:</span> <input type="text" name="action" value="child_categories">
 		<br><input type="submit" value="xml Kategorien holen" class="submitbtn">
</form>
<h3>newCat</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">parent_cat:</span> <input type="text" name="parent_cat" value="52">
 		<br><span class="title">name:</span> <input type="text" name="name" value="neue kategorie">
 		<br><span class="title">action:</span> <input type="text" name="action" value="newCat">
 		<br><input type="submit" value="neue kategorie anlegen" class="submitbtn">
</form>
<h3>parent_categories</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">catid: </span><input type="text" name="catid" value="74">
 		<br><span class="title">action:</span> <input type="text" name="action" value="parent_categories">
 		<br><input type="submit" value="xml überkategorien holen" class="submitbtn">
</form>

<h3>getCompetences</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="getCompetences">
 		<br><span class="title">item_id:</span> <input type="text" name="itemid" value="210">
 		<br><span class="title">subjectid:</span> <input type="text" name="subjectid" value="">
 		<br><input type="submit" value="get competencies" class="submitbtn">
</form>

<h3>save_selected_competences</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="save_selected_competences">
 		<br><span class="title">itemid:</span> <input type="text" name="item_id" value="210">
 		<br><span class="title">subject_id:</span> <input type="text" name="subject_id" value="">
 		<br><span class="title">selected_competences:</span> <input type="text" name="selected_competences" value="">
 		
 		
 		<br><input type="submit" value="save competencies" class="submitbtn">
</form>

<h3>getTopics</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="getTopics">
 		<br><input type="submit" value="get Topics" class="submitbtn">
</form>
<h3>getSubjects</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="getSubjects">
 		<br><input type="submit" value="get Subjects" class="submitbtn">
</form>

<h3>getExamples</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="getExamples">
 		<br><input type="submit" value="get examples" class="submitbtn">
</form>

 	<h3>all_items</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="all_items">
 		<br><input type="submit" value="get items" class="submitbtn">
</form>
 	<h3>all_users</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="all_users">
 		<br><input type="submit" value="get items" class="submitbtn">
</form>

	<h3>getViews</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="getViews">
 		<br><input type="submit" value="get views" class="submitbtn">
</form>

	<h3>delete_view</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="delete_view">
 		 		<br><span class="title">viewid:</span> <input type="text" name="viewid" value="">
 		<br><input type="submit" value="get views" class="submitbtn">
</form>

<h3>get_items_for_view</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">view_id:</span> <input type="text" name="view_id" value="10">
 		
 		<br><span class="title">action:</span> <input type="text" name="action" value="get_items_for_view">
 		<br><input type="submit" value="get items" class="submitbtn">
</form>
<h3>get_users_for_view</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">view_id:</span> <input type="text" name="view_id" value="10">
 		
 		<br><span class="title">action:</span> <input type="text" name="action" value="get_users_for_view">
 		<br><input type="submit" value="get users" class="submitbtn">
</form>

<h3>save_view_title</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">view_id:</span> <input type="text" name="view_id" value="10">
 		
 		<br><span class="title">action:</span> <input type="text" name="action" value="save_view_title">
 		<br><span class="title">title:</span> <input type="text" name="title" value="neuer title">
 		<br><span class="title">description:</span> <input type="text" name="description" value="neuer description">
<br><input type="submit" value="save" class="submitbtn">
</form>

<h3>save_selected_items</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="save_selected_items">
 		<br><span class="title">view_id:</span> <input type="text" name="view_id" value="10">
 		<br><span class="title">selected items:</span> <input type="text" name="selected_items" value="10_19_52_">
 		<br><span class="title">text:</span> <input type="text" name="text" value="meine text">
 		
<br><input type="submit" value="save items" class="submitbtn">
</form>
<h3>save_selected_user</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="save_selected_user">
 		<br><span class="title">view_id:</span> <input type="text" name="view_id" value="10">
 		<br><span class="title">shareall:</span> <input type="text" name="shareall" value="1">
 		<br><span class="title">externaccess:</span> <input type="text" name="externaccess" value="1">
 		
 		<br><span class="title">selected_user:</span> <input type="text" name="selected_user" value="8_9_">
 		
<br><input type="submit" value="save user" class="submitbtn">
</form>

<h3>get_Extern_Link</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="get_Extern_Link">
 		<br><span class="title">view_id:</span> <input type="text" name="view_id" value="10">

<br><input type="submit" value="get extern link" class="submitbtn">
</form>

<h3>delete item</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="delete_item">
 		<br><span class="title">itemid:</span> <input type="text" name="itemid" value="10">

<br><input type="submit" value="delete item" class="submitbtn">
</form>

<h3>Özeps import-flag zurücksetzen</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="oezepsinstalltonull">
 		<br><input type="submit" value="flag zurücksetzen" class="submitbtn">
</form>

<h3>Özeps item löschen</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="deleteFile_OezepsExample">
 		<br><span class="title">itemid:</span> <input type="text" name="id" value="10">
 		
 		<br><input type="submit" value="Özeps eigene Datei löschen" class="submitbtn">
</form>

<h3>alle meine Özeps items löschen</h3>
<form action="http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/epop.php" method="post" enctype="multipart/form-data">
 		<br><span class="title">key:</span> <input type="text" name="key" value="4c7c15b4ac31e83bcbdd804f36159">
 		<br><span class="title">action:</span> <input type="text" name="action" value="delete_all_oezeps">
 		<br><input type="submit" value="Özeps alle meine einträge löschen" class="submitbtn">
</form>
 </body>
</html>