<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

// Informal German overrides for the parent language pack (de).
$string['explaincategories'] = '<span class=\'block_eportfolio_bold\'>Kategorien:</span> Hier kannst du die Kategorien einsehen und bearbeiten.';
$string['invalidparentcategory'] = 'Wähle bitte eine gültige übergeordnete Kategorie.';
$string['explainingmoredata'] = '<strong>Dies ist dein Bereich, um Belege für deinen Lernweg zu sammeln.</strong>
			<ul>
				<li><strong>Füge deine besten Arbeiten hinzu:</strong> Lade Dateien, Weblinks oder Beschreibungen von Projekten, Präsentationen und mehr hoch.</li>
				<li><strong>Reflektiere über deine Erfahrungen:</strong> Erkläre für jeden Eintrag, was du gelernt hast, welche Herausforderungen du bewältigt hast und welche Erfolge du erzielt hast.</li>
			</ul>
			<strong>Betrachte jeden Eintrag als eine Art Geschichte, die deine Fähigkeiten und dein Wissen beschreibt.</strong>';
$string['explainingshare'] = 'Um den Link für andere Teilnehmer/innen zu veröffentlichen, klicke auf den jeweiligen Namen und speichere dann die Änderungen. Du kannst mehrere Teilnehmer/innen auswählen.';
$string['explainingsharefile'] = 'Um die Datei für andere Teilnehmer/innen zu veröffentlichen, klicke auf den jeweiligen Namen und speichere dann die Änderungen. Du kannst mehrere Teilnehmer/innen auswählen.';
$string['explainingsharenote'] = 'Um die Notiz für andere Teilnehmer/innen zu veröffentlichen, klicke auf den jeweiligen Namen und speichere dann die Änderungen. Du kannst mehrere Teilnehmer/innen auswählen.';
$string['mandatoryfields'] = 'Eine Kategorienbezeichnung und eine kurze Beschreibung sind erforderlich. Bitte fülle beides aus.';
$string['mandatoryfieldsforcat'] = 'Eine Feldbezeichnung ist erforderlich. Bitte fülle diese aus.';
$string['myportfoliotitle'] = 'ePortfolio-Modul: Füge hier Links und Dateien hinzu.';
$string['emailaccessdescription'] = 'Gib eine E-Mail-Adresse pro Zeile ein.<br />Jede Adresse erhält einen eindeutigen Link mit Zugriff auf diese Sammelmappe. Wenn du eine Adresse entfernst, erlischt der Zugriff über den zugehörigen Link.';
$string['emailaccessmessagesubject'] = 'Eine Sammelmappe wurde mit dir geteilt';
$string['emailaccessmessage'] = 'Hallo! {$a->sendername} hat die Sammelmappe "{$a->viewname}" mit dir geteilt. Sie kann hier abgerufen werden: {$a->url}';
$string['emailaccessmessageHTML'] = 'Hallo!<br />{$a->sendername} hat die Sammelmappe "{$a->viewname}" mit dir geteilt.<br />Sie kann <a href="{$a->url}">hier</a> abgerufen werden';
$string['emailaccessmessagesubject_updatedview'] = '{$a->sendername} hat Änderungen an einer Sammelmappe vorgenommen, die mit dir geteilt ist.';
$string['emailaccessmessage_updatedview'] = 'Hallo! {$a->sendername} hat die mit dir geteilte Sammelmappe "{$a->viewname}" aktualisiert. Sie kann hier abgerufen werden: {$a->url}';
$string['emailaccessmessageHTML_updatedview'] = 'Hallo!<br />{$a->sendername} hat die mit dir geteilte Sammelmappe "{$a->viewname}" aktualisiert.<br />Sie kann <a href="{$a->url}">hier</a> abgerufen werden';
$string['deletecategoryconfirm'] = 'Möchtest du die Kategorie "{$a->name}" wirklich löschen? Alle Einträge in dieser Kategorie und alle Unterkategorien werden gelöscht!';
$string['make_it_yours'] = 'Mach es zu deinem Eigenen';
$string['explainexport'] = '<span class=\'block_eportfolio_center\'><span class=\'block_eportfolio_bold\'>Export:</span> Hier kannst du dein ePortfolio exportieren</span>';
$string['clicktodownloadscorm'] = 'Drück den folgenden Button, um die SCORM-Datei herunterzuladen:';
$string['explainimport'] = 'Hier kannst du SCORM-Dateien ins Portfolio importieren. Wenn Marker gefunden werden, die darauf hinweisen, dass diese SCORM-Datei zuvor aus dem Portfolio-Modul exportiert wurde, so wird versucht, die Struktur entsprechend zu importieren.';
$string['contentaftersave'] = 'Speichere die Seite. Anschließend kannst du unter Inhalt Dateien hinzufügen.';
$string['disableditemwriteapi'] = 'Dieser alte Schreibdienst für Einträge ist deaktiviert. Verwende die strukturierte Inhaltsoberfläche.';
$string['deletenoteconfirm'] = 'Möchtest du diese Notiz wirklich löschen?';
$string['deletelinkconfirm'] = 'Möchtest du diesen Link wirklich löschen?';
$string['deletefileconfirm'] = 'Möchtest du diese Datei wirklich löschen?';
$string['exportcomment'] = 'Dies ist eine exportierte Datei von Exabis ePortfolio. Bitte entferne die Kommentare nicht, da sie sonst nicht mehr mitimportiert werden können!';
$string['loginasmode'] = 'Wenn du als andere Person angemeldet bist, hast du keinen Zugriff auf Portfolios.';
$string['comment_delete_confirmation'] = 'Möchtest du diesen Kommentar wirklich löschen?';
$string['nothingsharedwithme'] = 'Es wurde nichts mit dir geteilt';
$string['nothingsharedbyme'] = 'Du hast nichts geteilt';
$string['exportviewselect'] = 'Um alle Einträge einer bestimmten Sammelmappe zu exportieren, wähle diese bitte hier aus.';
$string['noviews'] = 'Lege bitte eine Sammelmappe an';
$string['viewitems'] = 'Deine Seiten';
$string['view_specialitem_text_help'] = 'Der Block mit individuellem Text. Du kannst den Text nach Belieben formatieren.';
$string['createpage'] = 'Zieh die Elemente in das Feld darunter, um deinen Inhalt zu erstellen.';
$string['chooselayout'] = 'Wähle ein Layout für deine Sammelmappe.';
$string['view_template_content_instructions'] = 'Erstelle den Inhalt mit Überschriften und formatiertem Text. Übernimm die Änderungen an den Bausteinen und speichere dann die Vorlage.';
$string['personalinformation_help'] = 'Der Block mit deinen persönlichen Daten wie Name und E-Mail-Adresse.<br><a href=\'{$a}\' target=\'_blank\' class=\'external-link\'>Profil anzeigen</a>';
$string['cvinformation_help'] = 'Der Block mit deinen Lebenslaufdaten aus Exabis ePortfolio, wie: Ausbildung, Beschäftigung usw.<br><a href=\'{$a}\' target=\'_blank\' class=\'external-link\'>Lebenslauf anzeigen</a>';
$string['items_help'] = 'Der Block mit deinen Seiten<br><a href=\'{$a}\' target=\'_blank\' class=\'external-link\'>Deine Seiten anzeigen</a>';
$string['show_items_from_other_users'] = 'Einträge von anderen Nutzer/innen in deinen geteilten Kategorien anzeigen';
$string['show_items_from_other_users_help'] = 'Falls du eine oder mehrere deiner Kategorien geteilt hast, können andere Nutzer/innen Einträge zu deinen Kategorien hinzufügen. Mit diesem Kontrollkästchen kannst du entscheiden, ob du die Einträge sehen möchtest.';
$string['desp_categories'] = '
    Erzählungen, Rollenspielszenen, Gedichte, Collagen
    Lebenslauf
    Berichte über Theateraufführungen, Ausstellungen
    Berichte über Projekte, Projektreflexionen
    Audio- und Videoclips
    Begegnungen durch Reisen in andere Länder
    Überlegungen zu deinem Sprachenlernen
    Zeugnisse
    Zertifikate
    Bestätigungen
    Hören
    Einzelarbeit
    Partnerarbeit
    Gruppenarbeit
    Lesen
    Einzelarbeit
     - Partnerarbeit
     - Gruppenarbeit
    An Gesprächen teilnehmen
     - Einzelarbeit
     - Partnerarbeit
     - Gruppenarbeit
    Zusammenhängend sprechen
     - Einzelarbeit
     - Partnerarbeit
     - Gruppenarbeit
    Schreiben
     - Einzelarbeit
     - Gruppenarbeit
     - Partnerarbeit
';
$string['selectcomps'] = 'Wähle hier die Kompetenzen, die zu deinem Upload passen!';
$string['studentcomps'] = 'Hier kannst du einschätzen, ob folgende Kompetenzen durch deine Seite erreicht wurden.';
$string['teachercomps'] = 'Hier kannst du beurteilen, ob die Teilnehmer/innen folgende Kompetenzen durch Seiten erreicht haben.';
$string['block_exaport_confirm_profilefield_create'] = 'Möchtest du wirklich das Profilfeld für vertrauenswürdige Trainer/innen erstellen?';
$string['resume_deleteeduconfirm'] = 'Möchtest du wirklich den Eintrag löschen?';
$string['resume_deleteemployconfirm'] = 'Möchtest du wirklich den Eintrag löschen?';
$string['resume_deletecertifconfirm'] = 'Möchtest du wirklich den Eintrag löschen?';
$string['resume_deletepublicconfirm'] = 'Möchtest du wirklich den Eintrag löschen?';
$string['resume_deletembrshipconfirm'] = 'Möchtest du wirklich den Eintrag löschen?';
$string['resume_deletelinkedinconfirm'] = 'Möchtest du wirklich den Eintrag löschen?';
$string['resume_exportto_europass_intro'] = '<p>Das Tool hilft den Nutzer/innen bei der Ermittlung von Lern- und Karrieremöglichkeiten. Im Hinblick auf Beratung und Service-Angebot ist das Profil auch für Arbeitgeber/innen, Freiwilligenorganisationen, Recruiter/innen, Laufbahnberater/innen, Jugendorganisationen, Bildungseinrichtungen und andere Organisationen interessant.</p>
        <p>Du kannst deinen Lebenslauf aus Exabis ePortfolio in dein Europass-Profil übernehmen.<br>
            Importiere die erzeugte XML-Datei auf der Seite <a href="https://europa.eu/europass/eportfolio/screen/cv-editor?lang=de" target="_blank">Hier kannst du deinen Lebenslauf erstellen</a><br>
            (Melde dich am besten vorher bei Europass an.)<br>
            Klicke auf „<strong>Europass-Lebenslauf importieren</strong>“ und lade die XML-Datei hoch, die du über die unten stehende Schaltfläche erzeugen kannst.
        </p>

    Ein paar Kurznotizen:<br>
    <ul><li>Der generierte Lebenslauf sollte auf alle Fälle nochmals kontrolliert werden</li>
        <li>Persönliche Daten werden aus den persönlichen Moodle-Informationen generiert</li>
        <li>Alle Datumsangaben sollten überprüft werden</li>
        <li>Bilder die über die HTML-Editoren eingefügt wurden werden nicht exportiert</li>
        <li>Texte die über die HTML-Editoren eingefügt wurden müssen unter Umständen neu formatiert werden</li>
        <li>Dateianhänge werden beim Export für Europass nicht in die XML-Datei aufgenommen.</li>
    </ul>';
$string['sharedArtefacts'] = 'Seiten, die über Kategorien mit dir geteilt wurden';
$string['shareditems_category'] = 'Gruppen-Portfolios, die mit dir geteilt wurden';
$string['i_shared'] = '{$a->sendername} hat die Sammelmappe {$a->title} mit dir geteilt';
$string['i_updated'] = '{$a->sendername} hat Änderungen an der Sammelmappe {$a->title} vorgenommen, welche mit dir geteilt ist.';
$string['pdf_settings_description'] = 'Wenn du spezielle oder ungewöhnliche Schriftzeichen verwendest, kann es zu Problemen mit dem generierten PDF kommen. Du kannst daher versuchen, unterschiedliche Schriftarteinstellungen zu verwenden.<br>
			Deine PDF-Einstellungen werden für diese Sammelmappe gespeichert.<br>Wenn deine Sammelmappe mit anderen Nutzer/innen geteilt wird, sehen diese das PDF mit deinen Einstellungen.<br>
			Nicht-standardisierte Schriftarten können einige Optionen wie Fett, Kursiv usw. verlieren.';
$string['pdf_customfont_help'] = 'Du kannst deine eigene .ttf-Datei hochladen. Wir werden versuchen, sie für die PDF-Erstellung zu verwenden.';
$string['settings_layout_settings_description'] = 'Hier kannst du einige Layout-Optionen der Sammelmappe ändern oder eigene nutzerdefinierte CSS-Regeln verwenden, um die Sammelmappe nach deinen Wünschen zu gestalten.<br>Diese Einstellungen werden als Standardeinstellungen <strong>für alle Sammelmappen</strong> verwendet.';
$string['layout_settings_description'] = 'Hier kannst du einige Layout-Optionen der Sammelmappe ändern oder eigene nutzerdefinierte CSS-Regeln verwenden, um die Sammelmappe nach deinen Wünschen zu gestalten.';
$string['layout_settings_custom_css_description'] = 'Hier kannst du eigene nutzerdefinierte CSS-Regeln verwenden.';
$string['forms.item.project_description_help'] = '<strong>Erkläre kurz den Zweck deiner Arbeit:</strong>
			<ul>
				<li>Welches Problem wurde damit angesprochen?</li>
				<li>Welche Fähigkeiten wolltest du entwickeln?</li>
				<li>Was war der Kontext?</li>
			</ul>';
$string['forms.item.project_description_editor_help'] = '<strong>Erkläre kurz den Zweck deiner Arbeit:</strong>
			<ul>
				<li>Welches Problem wurde angesprochen?</li>
				<li>Welche Fähigkeiten wolltest du entwickeln?</li>
				<li>Was war der Kontext?</li>
			</ul>';
$string['forms.item.project_process_help'] = '<strong>Beschreibe deinen Weg:</strong>
			<ul>
				<li>Welche Schritte hast du unternommen, um dieses Projekt abzuschließen?</li>
				<li>Welche Herausforderungen sind dir begegnet und wie hast du sie gemeistert?</li>
				<li>Welche Ressourcen hast du genutzt?</li>
			</ul>';
$string['forms.item.project_process_editor_help'] = '<strong>Beschreibe deinen Weg:</strong>
			<ul>
				<li>Welche Schritte hast du unternommen, um dieses Projekt abzuschließen?</li>
				<li>Welche Herausforderungen sind dir begegnet und wie hast du sie gemeistert?</li>
				<li>Welche Ressourcen hast du genutzt?</li>
			</ul>';
$string['forms.item.project_result_help'] = '<strong>Fasse die Ergebnisse deines Projekts kurz zusammen:</strong>
			<ul>
				<li>Was hast du erreicht?</li>
				<li>Welche Fähigkeiten und Kompetenzen hast du durch diese Arbeit entwickelt oder gestärkt?</li>
				<li>Was hast du über dich und deine Fähigkeiten gelernt?</li>
				<li>Was würdest du das nächste Mal anders machen?</li>
			</ul>';
$string['forms.item.project_result_editor_help'] = '<strong>Fasse die Ergebnisse deines Projekts kurz zusammen:</strong>
			<ul>
				<li>Was hast du erreicht?</li>
				<li>Welche Fähigkeiten und Kompetenzen hast du durch diese Arbeit entwickelt oder gestärkt?</li>
				<li>Was hast du über dich und deine Fähigkeiten gelernt?</li>
				<li>Was würdest du das nächste Mal anders machen?</li>
			</ul>';
$string['what_is_embed_code_content'] = '<p>Einbettungscode ist ein Code, der von einer Drittanbieter-Website wie YouTube oder Twitter generiert wird und den du kopieren und auf deiner eigenen Webseite einfügen kannst. Dieser eingebettete Code zeigt dann dasselbe Medium, dieselbe Anwendung oder denselben Feed auf deiner Webseite wie im Original.</p>
			<p>Wir bieten Anleitungen für die beliebtesten Videoplattformen wie YouTube oder Vimeo.</p>
			<p><strong>So findest du den Einbettungscode auf YouTube:</strong></p>
			<ol>
				<li>Gehe zu YouTube.</li>
				<li>Gehe zu dem Video, das du einbetten möchtest.</li>
				<li>Klicke auf den <strong>Teilen-Link</strong> unter dem Video und dann auf den <strong>Einbetten-Link</strong>.</li>
				<li>Klicke auf die Schaltfläche <strong>Kopieren</strong> im unteren Bereich des geöffneten Fensters.</li>
				<li>Der Einbettungslink wird blau markiert und in die Zwischenablage kopiert.</li>
				<li>Füge den Code in das Textfeld von exabis E-portfolio ein.</li>
			</ol>
			<p><strong>So findest du den Einbettungscode auf Vimeo:</strong></p>
			<ol>
				<li>Gehe zu Vimeo.</li>
				<li>Gehe zu dem Video, das du einbetten möchtest.</li>
				<li>Klicke auf die Schaltfläche <strong>Teilen</strong> oben rechts im Video.</li>
				<li>Ein Popup-Fenster mit den Einbettungslink-Informationen wird angezeigt. Kopiere nur den <strong>&lt;<i>iframe</i>&gt;</strong>-Teil des Einbettungslinks.
					Beispiel:<br>
					<ol>
						<li>
							Der gesamte Einbettungslink zeigt Folgendes:<br>
							<textarea disabled=\'true\' style=\'border: none;background-color:white;width:100%;font-size:0.75em;\'>&lt;iframe src=&quot;https://player.vimeo.com/video/1233456789&quot; width=&quot;640&quot; height=&quot;360&quot; frameborder=&quot;0&quot; allow=&quot;autoplay; fullscreen&quot; allowfullscreen&gt;&lt;/iframe&gt;&lt;p&gt;&lt;a href=&quot;https://vimeo.com/1233456789&quot;&gt;1- Automation Opening[2]&lt;/a&gt; von &lt;a href=&quot;https://vimeo.com/user98765432&quot;&gt;R1 Marketing&lt;/a&gt; auf &lt;a href=&quot;https://vimeo.com&quot;&gt;Vimeo&lt;/a&gt;.&lt;/p&gt;</textarea>
						</li>
						<li>
							Kopiere nur den <strong>&lt;<i>iframe</i>&gt;</strong>-Teil:<br>
							<textarea disabled=\'true\' style=\'border: none;background-color:white;width:100%;font-size:0.75em;\'>&lt;iframe src=&quot;https://player.vimeo.com/video/1233456789&quot; width=&quot;640&quot; height=&quot;360&quot; frameborder=&quot;0&quot; allow=&quot;autoplay; fullscreen&quot; allowfullscreen&gt;&lt;/iframe&gt;</textarea>
						</li>
					</ol>
				<li>Füge diesen Code in das Textfeld von exabis E-portfolio ein.</li>
			</ol>
            ';
$string['whyEportfolio_description'] = '<h4>Mach Arbeit sichtbar: dein digitales Portfolio</h4>
            <br>
            <p>Du investierst viel Zeit und Energie in Aufgaben, Projekte oder Weiterbildungen. Oft verschwinden diese Ergebnisse jedoch nach der Abgabe oder dem Kursende in der Schublade, dabei spiegeln sie dein Können, deine Kreativität und dein Wissen wider.</p>
            <p>Mit einem <strong>ePortfolio</strong> hast du die Möglichkeit, deine Arbeiten, erworbenen Kompetenzen und Lernfortschritte sichtbar zu machen. So entsteht eine Sammlung, die du nicht nur für dich selbst dokumentieren, sondern auch mit Lehrenden, Institutionen oder potenziellen Arbeitgeber/innen teilen kannst.</p>
            <p>Von Präsentationen über Projektberichte bis hin zu Videos oder Zertifikaten – dein Portfolio macht Leistungen greifbar. Auch Microcredentials können integriert werden und unterstreichen deine Erfolge in Aus- und Weiterbildung.</p>
            <br>
            <h4>Was ist ein ePortfolio?</h4>
            <br>
            <p>Ein ePortfolio ist mehr als nur ein digitaler Lebenslauf. Es ist eine persönliche Sammlung deiner Arbeiten, die deine Fähigkeiten und Erfahrungen lebendig darstellt. Anders als ein klassischer CV bietet es echte Nachweise: Arbeitsproben, Reflexionen und den Kontext, in dem du deine Kompetenzen erworben hast.</p>
            <p>Mit Exabis ePortfolio kannst du mehrere Sammelmappen erstellen, passgenau für bestimmte Bewerbungen, Jobchancen oder Präsentationen. So zeigst du immer das, was für die jeweilige Situation am wichtigsten ist.</p>
            <br>
            <h4>Wie erstellst du dein ePortfolio?</h4>
            <br>
            <ol>
                <li><strong>Sammeln:</strong> Trag deine Arbeiten zusammen, seien es Aufgaben, Präsentationen, Projekte, Videos oder Ähnliches.</li>
                <li><strong>Auswählen:</strong> Entscheide dich für deine besten Arbeiten, die deine Stärken am deutlichsten zeigen.</li>
                <li><strong>Reflektieren:</strong> Ergänze kurze Beschreibungen, die deine Kompetenzen verdeutlichen und den Kontext erklären.</li>
                <li><strong>Gestalten:</strong> Bau ein Portfolio Schritt für Schritt auf und entwickle es laufend weiter.</li>
            </ol>
            <p>Mit Exabis ePortfolio geht das unkompliziert, flexibel und effizient. Du hast jederzeit die Kontrolle darüber, wie du dich und dein Können präsentierst, und machst sichtbar, was dich einzigartig macht.</p>';
$string['cofigureblock_cvinfo_help'] = 'Du siehst den aktuellen Status der ausgewählten Elemente in deinem Lebenslauf. Der Blockinhalt wird aktualisiert, wenn du später Änderungen an Elementen in deinem Lebenslauf vornimmst.';
$string['create_view_content_help_title'] = 'Gestalte deine einzigartige Geschichte: Erstelle dein Portfolio';
$string['create_view_content_help_text'] = '<ul>
				<li><strong>Plane dein Layout:</strong> Zieh Bausteine an die gewünschte Stelle und arrangiere sie, um deine Geschichte zu erzählen. Verwende Spalten (verfügbar im Abschnitt "Layout"), um dein Portfolio zu planen.</li>
				<li><strong>Sorg für Übersichtlichkeit:</strong> Verwende Überschriften, Links und Notizen, um deine Inhalte effektiv zu strukturieren.</li>
				<li><strong>Personalisiere deinen Stil:</strong> Wähle Schriftarten und Farben innerhalb der verfügbaren Optionen, um deinem Portfolio eine persönliche Note zu geben.</li>
			</ul>
			<strong>Denk daran: Dein e-Portfolio erzählt der Welt von dir und deinen Fähigkeiten. Mach es klar, prägnant und ansprechend!</strong>';
$string['badges_help'] = 'Der Block mit dir verliehenen Badges<br><a href=\'{$a}\' target=\'_blank\' class=\'external-link\'>Deine Badges anzeigen</a>';
$string['block_exaport_used_layout_body'] = 'Ändere das Layout, falls es Darstellungsprobleme gibt. Momentan wird es nur für die Seitenliste verwendet.';
$string['starter_template_load_confirm'] = 'Das Laden dieser Vorlage ersetzt die aktuelle Kursvorlage. Dies wirkt sich erst auf Teilnehmer/innen aus, wenn du verteilst. Fortfahren?';
$string['template_empty'] = 'Keine Vorlage definiert. Lade eine Starter-Vorlage oder erstelle Kategorien manuell.';
$string['no_template_to_distribute'] = 'Keine Vorlage zu verteilen. Bitte erstelle oder lade zuerst eine Vorlage.';
$string['capability_required'] = 'Du hast keine Berechtigung zur Verwaltung von Strukturvorgaben';
$string['confirm_distribute_categories_body'] = 'Bist du sicher, dass du die Kategoriestruktur an alle eingeschriebenen Teilnehmer/innen verteilen möchtest? Dies erstellt Kategorien für Teilnehmer/innen, die diese noch nicht haben. Diese Aktion kann nicht rückgängig gemacht werden. Sobald verteilt, werden die Kategorien erstellt.';
$string['confirm_distribute_views_body'] = 'Bist du sicher, dass du die Sammelmappenvorlagen an alle eingeschriebenen Teilnehmer/innen verteilen möchtest? Dies erstellt Sammelmappen für Teilnehmer/innen, die diese noch nicht haben. Diese Aktion kann nicht rückgängig gemacht werden. Sobald verteilt, werden die Sammelmappen erstellt.';
$string['view_distribution_description'] = 'Verteile Sammelmappen-Vorlagen an Teilnehmer/innen. Sammelmappen helfen Teilnehmer/innen, ihre Arbeit zu präsentieren.';
$string['view_template_empty'] = 'Keine Sammelmappen in der Vorlage. Füge eine Sammelmappe hinzu, um zu beginnen.';
$string['remove_view_confirm'] = 'Bist du sicher, dass du diese Sammelmappe aus der Vorlage entfernen möchtest? Dies löscht keine bestehenden Sammelmappen von Teilnehmer/innen.';
$string['sharing_disable_confirm'] = 'Wenn du mit deaktivierter Freigabe speicherst, wird der Zugriff widerrufen und die aktuellen Personen- und Gruppenzuweisungen werden entfernt.';
$string['exacompupdaterequired'] = 'Exaport kann mit dem vorhandenen Exacomp nicht installiert oder aktualisiert werden. Bitte aktualisiere zuerst den Exacomp-Code auf Version {$a} oder neuer und starte das Moodle-Upgrade erneut. Exaport kann auch ohne Exacomp verwendet werden.';
$string['sharing_load_users_error'] = 'Die Nutzerliste konnte nicht geladen werden. Bitte versuche es erneut.';
$string['sharing_users_not_ready'] = 'Die Empfängerliste ist noch nicht vollständig geladen. Bitte warte oder lade die Seite neu, bevor du speicherst.';
$string['sharing_load_groups_error'] = 'Die Gruppenliste konnte nicht geladen werden. Bitte versuche es erneut.';
$string['sharing_groups_not_ready'] = 'Die Gruppenauswahl ist noch nicht geladen. Bitte warte oder versuche es erneut, bevor du speicherst.';
$string['settings_copied_template_root_name_description'] = 'Verwende {coursefullname} für den vollständigen Namen des Zielkurses. Ein leeres Muster verwendet Ausbildungsdokumente {coursefullname}. Namen werden auf die bestehende Grenze von 255 Zeichen gekürzt.';
$string['audititemcontentitemid_help'] = 'Lass das Feld leer, um die gesamte Moodle-Instanz zu prüfen. Gib eine positive Eintrags-ID ein, um die Prüfung auf Befunde zu beschränken, die diesem Eintrag zugeordnet werden können.';
$string['audititemcontentperformancewarning'] = 'Bei einer großen Moodle-Installation kann die Prüfung der gesamten Instanz einige Zeit dauern. Sie startet erst, wenn du dieses Formular absendest.';
$string['audititemcontentpositive'] = 'Gib eine positive Eintrags-ID ein.';
$string['audititemcontentsamplerange'] = 'Gib für die Höchstzahl der Beispiel-IDs einen Wert zwischen 1 und 1000 ein.';
$string['itemcontentmigrationpaused'] = 'Das Zeitbudget der Migration von Eintragsinhalten für diesen Upgrade-Versuch wurde erreicht. Abgeschlossene Einträge wurden gespeichert. Starte das Moodle-Upgrade erneut, um fortzufahren. Abgeschlossene Einträge werden nicht dupliziert. Der abschließende Migrations-Speicherpunkt wurde noch nicht gesetzt.';
$string['linkedcontentnotice'] = 'Wähle die anzuzeigenden Informationen. Änderungen an ausgewählten Informationen erscheinen auch in geteilten Einträgen. Neue Lebenslauf-Einträge und Badges werden nicht automatisch hinzugefügt.';
$string['linkedcontentselectionrequired'] = 'Wähle mindestens ein verfügbares Feld, einen Lebenslauf-Eintrag oder ein erworbenes Badge.';
$string['linkedcontentmissing'] = 'Die ausgewählten Informationen sind nicht mehr verfügbar. Bearbeite diesen Inhaltsblock, um die Auswahl zu aktualisieren.';
$string['uploadtour_create_description'] = 'Erklärt, wo du Dokumente hochlädst und wie du eine Portfolio-Seite mit ihren Inhalten speicherst.';
$string['uploadtour_title_content'] = 'Wähle unter Inhalt + und dann Datei. Lade deine Datei hoch und wähle Hinzufügen. Speichere anschließend die Seite.';
$string['uploadtour_save_title'] = 'Deine Seite speichern';
$string['uploadtour_save_content'] = 'Gib deiner Seite einen Titel und wähle Speichern. Damit speicherst du die Seite und ihre Inhalte. Abbrechen verwirft deine ungespeicherten Änderungen.';
$string['uploadtour_content_title'] = 'Inhalte zu deiner Seite hinzufügen';
$string['uploadtour_content_content'] = 'Unter Inhalt fügst du Dokumente, Texte und Links hinzu. Änderungen werden erst mit der Seite gespeichert. Abbrechen in diesem Formular verwirft ungespeicherte Änderungen.';
$string['uploadtour_file_content'] = 'Wähle + und anschließend Datei. Lade dein Dokument hoch und wähle Übernehmen, um zu diesem Formular zurückzukehren. Speichern und schließen speichert die Seite und ihre Inhalte.';
$string['itemeditorinvalid'] = 'Einige Änderungen an der Seite fehlen oder sind ungültig. Deine Änderungen wurden nicht gespeichert.';
$string['itemeditorlocked'] = 'Diese Seite kann nicht mehr bearbeitet werden. Deine noch nicht gespeicherten Änderungen werden unten angezeigt. Es wurde jedoch nichts gespeichert.';
$string['itemeditorpendinghint_help'] = 'Speichere abschließend die Seite. Abbrechen verwirft ungespeicherte Änderungen.';
