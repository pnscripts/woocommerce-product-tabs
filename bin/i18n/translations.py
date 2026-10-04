#!/usr/bin/env python3
"""Bundled translations (bg_BG, de_DE, pl_PL) for PN Product Tabs for WooCommerce.

Regenerates languages/pnscripts-product-tabs-<locale>.po from the .pot; untranslated strings stay empty
(WordPress falls back to English). Run bin/i18n/update.sh, which also builds the .mo files.
"""
import re
import sys
from pathlib import Path

T = {
    "PN Product Tabs for WooCommerce": ("PN Product Tabs for WooCommerce", "PN Product Tabs for WooCommerce", "PN Product Tabs for WooCommerce"),
    "Custom product tabs per product, reusable global tabs by category or tag, FAQ tabs with optional FAQPage schema, rename, reorder or hide the default tabs, and a one-click importer from YIKES Custom Product Tabs. Works in classic and block themes.": (
        "Собствени раздели за всеки продукт, глобални раздели за категории или етикети, раздели с ЧЗВ и FAQPage schema, преименуване, подреждане и скриване на стандартните раздели и импорт с едно кликване от YIKES Custom Product Tabs. Работи с класически и блокови теми.",
        "Eigene Produkt-Tabs pro Produkt, wiederverwendbare globale Tabs nach Kategorie oder Schlagwort, FAQ-Tabs mit optionalem FAQPage-Schema, Umbenennen, Sortieren oder Ausblenden der Standard-Tabs und ein Ein-Klick-Import aus YIKES Custom Product Tabs. Funktioniert mit klassischen und Block-Themes.",
        "Własne zakładki dla każdego produktu, globalne zakładki według kategorii lub tagów, zakładki FAQ z opcjonalnym schematem FAQPage, zmiana nazwy, kolejności lub ukrywanie domyślnych zakładek oraz import jednym kliknięciem z YIKES Custom Product Tabs. Działa z motywami klasycznymi i blokowymi.",
    ),
    "PN Scripts": ("PN Scripts", "PN Scripts", "PN Scripts"),
    "Tab settings": ("Настройки на разделите", "Tab-Einstellungen", "Ustawienia zakładek"),
    "Tab type": ("Вид на раздела", "Tab-Typ", "Typ zakładki"),
    "Rich text: the content written in the editor above": ("Текст: съдържанието от редактора по-горе", "Text: der Inhalt aus dem Editor oben", "Tekst: treść wpisana w edytorze powyżej"),
    "FAQ: questions and answers below": ("ЧЗВ: въпросите и отговорите по-долу", "FAQ: Fragen und Antworten unten", "FAQ: pytania i odpowiedzi poniżej"),
    "Add question": ("Добави въпрос", "Frage hinzufügen", "Dodaj pytanie"),
    "Publish as FAQPage structured data": ("Публикувай като структурирани данни FAQPage", "Als strukturierte FAQPage-Daten ausgeben", "Publikuj jako dane strukturalne FAQPage"),
    "Show this tab on": ("Показвай раздела на", "Diesen Tab anzeigen bei", "Pokazuj tę zakładkę na"),
    "All products": ("Всички продукти", "Alle Produkte", "Wszystkich produktach"),
    "Products in these categories or with these tags": ("Продукти в тези категории или с тези етикети", "Produkte in diesen Kategorien oder mit diesen Schlagwörtern", "Produktach z tych kategorii lub z tymi tagami"),
    "Only products where I add it (Product data → Custom tabs)": ("Само продукти, в които го добавя (Данни за продукта → Собствени раздели)", "Nur Produkte, bei denen ich ihn hinzufüge (Produktdaten → Eigene Tabs)", "Tylko produktach, do których go dodam (Dane produktu → Własne zakładki)"),
    "Categories (subcategories included)": ("Категории (включително подкатегориите)", "Kategorien (inklusive Unterkategorien)", "Kategorie (łącznie z podkategoriami)"),
    "Choose categories…": ("Изберете категории…", "Kategorien wählen …", "Wybierz kategorie…"),
    "Tags": ("Етикети", "Schlagwörter", "Tagi"),
    "Choose tags…": ("Изберете етикети…", "Schlagwörter wählen …", "Wybierz tagi…"),
    "Position (priority)": ("Позиция (приоритет)", "Position (Priorität)", "Pozycja (priorytet)"),
    "Lower numbers come first. WooCommerce uses 10 for Description, 20 for Additional information and 30 for Reviews (change these under Products → Tab settings).": (
        "По-малките числа са първи. WooCommerce използва 10 за Описание, 20 за Допълнителна информация и 30 за Отзиви (променят се в Продукти → Настройки на разделите).",
        "Niedrigere Zahlen stehen zuerst. WooCommerce verwendet 10 für Beschreibung, 20 für Zusätzliche Informationen und 30 für Bewertungen (änderbar unter Produkte → Tab-Einstellungen).",
        "Niższe liczby są pierwsze. WooCommerce używa 10 dla Opisu, 20 dla Informacji dodatkowych i 30 dla Opinii (zmienisz to w Produkty → Ustawienia zakładek).",
    ),
    "Question": ("Въпрос", "Frage", "Pytanie"),
    "Answer": ("Отговор", "Antwort", "Odpowiedź"),
    "Remove question": ("Премахни въпроса", "Frage entfernen", "Usuń pytanie"),
    "Shown on": ("Показва се на", "Angezeigt bei", "Wyświetlana na"),
    "Type": ("Вид", "Typ", "Typ"),
    "Priority": ("Приоритет", "Priorität", "Priorytet"),
    "FAQ": ("ЧЗВ", "FAQ", "FAQ"),
    "Rich text": ("Текст", "Text", "Tekst"),
    "Products where added": ("Продукти, в които е добавен", "Produkte, bei denen er hinzugefügt wurde", "Produkty, do których ją dodano"),
    "No categories or tags chosen": ("Няма избрани категории или етикети", "Keine Kategorien oder Schlagwörter gewählt", "Nie wybrano kategorii ani tagów"),
    "Custom tabs": ("Собствени раздели", "Eigene Tabs", "Własne zakładki"),
    "Remove this tab? It is deleted when you update the product.": ("Да се премахне ли разделът? Изтрива се, когато обновите продукта.", "Diesen Tab entfernen? Er wird beim Aktualisieren des Produkts gelöscht.", "Usunąć tę zakładkę? Zostanie usunięta po zaktualizowaniu produktu."),
    "(no title)": ("(без заглавие)", "(kein Titel)", "(bez tytułu)"),
    "Tabs shown on this product, in this order (drag to reorder). Rich-text tabs accept anything the editor does; FAQ tabs show questions as an accessible list and can publish FAQPage structured data.": (
        "Раздели на този продукт в този ред (плъзнете, за да пренаредите). Текстовите раздели приемат всичко, което поддържа редакторът; разделите с ЧЗВ показват въпросите като достъпен списък и могат да публикуват структурирани данни FAQPage.",
        "Tabs dieses Produkts in dieser Reihenfolge (zum Sortieren ziehen). Text-Tabs akzeptieren alles, was der Editor kann; FAQ-Tabs zeigen Fragen als barrierefreie Liste und können strukturierte FAQPage-Daten ausgeben.",
        "Zakładki tego produktu w tej kolejności (przeciągnij, aby zmienić). Zakładki tekstowe przyjmują wszystko, co obsługuje edytor; zakładki FAQ pokazują pytania jako dostępną listę i mogą publikować dane strukturalne FAQPage.",
    ),
    "No custom tabs on this product yet.": ("Този продукт още няма собствени раздели.", "Dieses Produkt hat noch keine eigenen Tabs.", "Ten produkt nie ma jeszcze własnych zakładek."),
    "Add tab": ("Добави раздел", "Tab hinzufügen", "Dodaj zakładkę"),
    "Add FAQ tab": ("Добави раздел с ЧЗВ", "FAQ-Tab hinzufügen", "Dodaj zakładkę FAQ"),
    "Global tab to add": ("Глобален раздел за добавяне", "Hinzuzufügender globaler Tab", "Globalna zakładka do dodania"),
    "Add a global tab…": ("Добави глобален раздел…", "Globalen Tab hinzufügen …", "Dodaj globalną zakładkę…"),
    "Add": ("Добави", "Hinzufügen", "Dodaj"),
    "Manage global tabs": ("Управление на глобалните раздели", "Globale Tabs verwalten", "Zarządzaj globalnymi zakładkami"),
    "Global tabs shown automatically": ("Глобални раздели, показвани автоматично", "Automatisch angezeigte globale Tabs", "Globalne zakładki wyświetlane automatycznie"),
    'Hide "%s" on this product': ("Скрий „%s“ на този продукт", "„%s“ bei diesem Produkt ausblenden", "Ukryj „%s” na tym produkcie"),
    "WooCommerce tabs on this product": ("Раздели на WooCommerce в този продукт", "WooCommerce-Tabs bei diesem Produkt", "Zakładki WooCommerce w tym produkcie"),
    "(deleted global tab)": ("(изтрит глобален раздел)", "(gelöschter globaler Tab)", "(usunięta zakładka globalna)"),
    "Global": ("Глобален", "Global", "Globalna"),
    "Drag to reorder": ("Плъзнете, за да пренаредите", "Zum Sortieren ziehen", "Przeciągnij, aby zmienić kolejność"),
    "Imported": ("Импортиран", "Importiert", "Zaimportowana"),
    "Show": ("Показвай", "Anzeigen", "Pokazuj"),
    "Remove": ("Премахни", "Entfernen", "Usuń"),
    "Content comes from the global tab and updates everywhere when you edit it.": ("Съдържанието идва от глобалния раздел и се обновява навсякъде, когато го редактирате.", "Der Inhalt stammt aus dem globalen Tab und ändert sich überall, wenn Sie ihn bearbeiten.", "Treść pochodzi z globalnej zakładki i aktualizuje się wszędzie, gdy ją edytujesz."),
    "Edit global tab": ("Редактирай глобалния раздел", "Globalen Tab bearbeiten", "Edytuj globalną zakładkę"),
    "Tab title": ("Заглавие на раздела", "Tab-Titel", "Tytuł zakładki"),
    "Tab content": ("Съдържание на раздела", "Tab-Inhalt", "Treść zakładki"),
    "Description": ("Описание", "Beschreibung", "Opis"),
    "Additional information": ("Допълнителна информация", "Zusätzliche Informationen", "Informacje dodatkowe"),
    "Reviews": ("Отзиви", "Bewertungen", "Opinie"),
    "Product tab settings": ("Настройки на продуктовите раздели", "Einstellungen der Produkt-Tabs", "Ustawienia zakładek produktu"),
    "Settings": ("Настройки", "Einstellungen", "Ustawienia"),
    "Global tabs": ("Глобални раздели", "Globale Tabs", "Globalne zakładki"),
    "Import the YIKES tabs now? YIKES data is not changed and you can undo the import.": ("Да се импортират ли разделите от YIKES сега? Данните на YIKES не се променят и можете да отмените импорта.", "YIKES-Tabs jetzt importieren? Die YIKES-Daten bleiben unverändert und der Import kann rückgängig gemacht werden.", "Zaimportować teraz zakładki YIKES? Dane YIKES nie są zmieniane, a import można cofnąć."),
    "Remove all tabs created by the import (tabs you added yourself stay)?": ("Да се премахнат ли всички раздели, създадени от импорта (добавените от вас остават)?", "Alle vom Import erstellten Tabs entfernen (selbst angelegte Tabs bleiben)?", "Usunąć wszystkie zakładki utworzone przez import (dodane przez Ciebie zostaną)?"),
    "Working… %d products handled": ("Обработка… %d обработени продукта", "In Arbeit … %d Produkte verarbeitet", "Trwa praca… przetworzono produktów: %d"),
    "The request failed. Reload the page and try again.": ("Заявката беше неуспешна. Презаредете страницата и опитайте отново.", "Die Anfrage ist fehlgeschlagen. Laden Sie die Seite neu und versuchen Sie es erneut.", "Żądanie nie powiodło się. Odśwież stronę i spróbuj ponownie."),
    "Dry run finished: nothing was changed. These numbers are what an import will do.": ("Пробното изпълнение приключи: нищо не е променено. Тези числа показват какво ще направи импортът.", "Probelauf beendet: Es wurde nichts geändert. Diese Zahlen zeigen, was ein Import tun wird.", "Próba zakończona: nic nie zostało zmienione. Te liczby pokazują, co zrobi import."),
    "Import finished. Check a few product pages, then deactivate YIKES Custom Product Tabs.": ("Импортът приключи. Проверете няколко продуктови страници и след това деактивирайте YIKES Custom Product Tabs.", "Import abgeschlossen. Prüfen Sie einige Produktseiten und deaktivieren Sie dann YIKES Custom Product Tabs.", "Import zakończony. Sprawdź kilka stron produktów, a potem dezaktywuj YIKES Custom Product Tabs."),
    "Import removed.": ("Импортът е премахнат.", "Import entfernt.", "Import usunięty."),
    "… and %d more notes like these.": ("… и още %d подобни бележки.", "… und %d weitere ähnliche Hinweise.", "… i jeszcze %d podobnych uwag."),
    "Products with YIKES tabs": ("Продукти с раздели от YIKES", "Produkte mit YIKES-Tabs", "Produkty z zakładkami YIKES"),
    "YIKES saved tabs": ("Запазени раздели в YIKES", "Gespeicherte YIKES-Tabs", "Zapisane zakładki YIKES"),
    "Global tabs to create / created": ("Глобални раздели за създаване / създадени", "Zu erstellende / erstellte globale Tabs", "Globalne zakładki do utworzenia / utworzone"),
    "Saved tabs imported before (left as they are)": ("Запазени раздели, импортирани преди (остават непроменени)", "Bereits importierte gespeicherte Tabs (bleiben unverändert)", "Zapisane zakładki zaimportowane wcześniej (bez zmian)"),
    "Products to import / imported": ("Продукти за импорт / импортирани", "Zu importierende / importierte Produkte", "Produkty do importu / zaimportowane"),
    "Products unchanged since the last import (skipped)": ("Продукти без промяна от последния импорт (пропуснати)", "Seit dem letzten Import unveränderte Produkte (übersprungen)", "Produkty bez zmian od ostatniego importu (pominięte)"),
    "Product tabs copied": ("Копирани продуктови раздели", "Kopierte Produkt-Tabs", "Skopiowane zakładki produktów"),
    "Product tabs linked to a global tab": ("Продуктови раздели, свързани с глобален раздел", "Mit einem globalen Tab verknüpfte Produkt-Tabs", "Zakładki produktów powiązane z globalną zakładką"),
    "Tabs without a title (imported switched off)": ("Раздели без заглавие (импортирани изключени)", "Tabs ohne Titel (deaktiviert importiert)", "Zakładki bez tytułu (zaimportowane jako wyłączone)"),
    "Tabs hidden by a duplicate title in YIKES (imported switched off)": ("Раздели, скрити от дублирано заглавие в YIKES (импортирани изключени)", "Durch doppelten Titel in YIKES verdeckte Tabs (deaktiviert importiert)", "Zakładki ukryte przez powtórzony tytuł w YIKES (zaimportowane jako wyłączone)"),
    "Default tabs YIKES replaced (hidden on those products)": ("Стандартни раздели, заменени от YIKES (скрити на тези продукти)", "Von YIKES ersetzte Standard-Tabs (bei diesen Produkten ausgeblendet)", "Domyślne zakładki zastąpione przez YIKES (ukryte w tych produktach)"),
    "Imported global tabs removed": ("Премахнати импортирани глобални раздели", "Entfernte importierte globale Tabs", "Usunięte zaimportowane zakładki globalne"),
    "Products cleaned": ("Почистени продукти", "Bereinigte Produkte", "Wyczyszczone produkty"),
    "You are not allowed to import tabs.": ("Нямате права да импортирате раздели.", "Sie dürfen keine Tabs importieren.", "Nie masz uprawnień do importu zakładek."),
    "Import from YIKES": ("Импорт от YIKES", "Import aus YIKES", "Import z YIKES"),
    "WooCommerce tabs": ("Раздели на WooCommerce", "WooCommerce-Tabs", "Zakładki WooCommerce"),
    "Rename, reorder or hide the standard tabs on every product (single products can hide them too, under Product data → Custom tabs). Leave a title empty to keep WooCommerce's title; in the Reviews title, %s becomes the number of reviews.": (
        "Преименувайте, пренаредете или скрийте стандартните раздели на всички продукти (отделни продукти също могат да ги скрият в Данни за продукта → Собствени раздели). Оставете заглавието празно, за да запазите заглавието на WooCommerce; в заглавието на Отзиви %s се заменя с броя на отзивите.",
        "Standard-Tabs bei allen Produkten umbenennen, sortieren oder ausblenden (einzelne Produkte können sie auch unter Produktdaten → Eigene Tabs ausblenden). Lassen Sie einen Titel leer, um den WooCommerce-Titel zu behalten; im Bewertungen-Titel wird %s durch die Anzahl der Bewertungen ersetzt.",
        "Zmień nazwę, kolejność lub ukryj standardowe zakładki we wszystkich produktach (pojedyncze produkty też mogą je ukryć w Dane produktu → Własne zakładki). Zostaw pusty tytuł, aby zachować tytuł WooCommerce; w tytule Opinii %s zamienia się w liczbę opinii.",
    ),
    "Tab": ("Раздел", "Tab", "Zakładka"),
    "Title": ("Заглавие", "Titel", "Tytuł"),
    "Priority of product tabs": ("Приоритет на продуктовите раздели", "Priorität der Produkt-Tabs", "Priorytet zakładek produktu"),
    "The first tab added on a product gets this priority, the next one +1, and so on. 25 places them between Additional information and Reviews. Global tabs have their own priority.": (
        "Първият раздел на продукта получава този приоритет, следващият +1 и т.н. 25 ги поставя между Допълнителна информация и Отзиви. Глобалните раздели имат собствен приоритет.",
        "Der erste Tab eines Produkts erhält diese Priorität, der nächste +1 usw. 25 platziert sie zwischen Zusätzliche Informationen und Bewertungen. Globale Tabs haben eine eigene Priorität.",
        "Pierwsza zakładka produktu otrzymuje ten priorytet, następna +1 itd. 25 umieszcza je między Informacjami dodatkowymi a Opiniami. Zakładki globalne mają własny priorytet.",
    ),
    "Display": ("Показване", "Anzeige", "Wyświetlanie"),
    "Keep Reviews as the last tab": ("Отзиви винаги като последен раздел", "Bewertungen immer als letzten Tab", "Opinie zawsze jako ostatnia zakładka"),
    "Repeat the tab title as a heading inside the tab (classic tabs; never inside accordions)": ("Повтаряй заглавието като заглавие в раздела (класически раздели; никога в акордеони)", "Tab-Titel als Überschrift im Tab wiederholen (klassische Tabs; nie in Akkordeons)", "Powtarzaj tytuł jako nagłówek w zakładce (klasyczne zakładki; nigdy w akordeonach)"),
    "Output FAQPage structured data for FAQ tabs that allow it (turn off if your SEO plugin already adds FAQ markup)": ("Извеждай структурирани данни FAQPage за разделите с ЧЗВ, които го позволяват (изключете, ако SEO разширението ви вече добавя FAQ маркиране)", "Strukturierte FAQPage-Daten für FAQ-Tabs ausgeben, die es erlauben (deaktivieren, wenn Ihr SEO-Plugin bereits FAQ-Markup hinzufügt)", "Wyświetlaj dane strukturalne FAQPage dla zakładek FAQ, które na to pozwalają (wyłącz, jeśli wtyczka SEO już dodaje znaczniki FAQ)"),
    "While YIKES Custom Product Tabs is still active, hide its copy of tabs that were imported": ("Докато YIKES Custom Product Tabs е активен, скривай неговото копие на импортираните раздели", "Solange YIKES Custom Product Tabs aktiv ist, dessen Kopie importierter Tabs ausblenden", "Dopóki YIKES Custom Product Tabs jest aktywny, ukrywaj jego kopie zaimportowanych zakładek"),
    "Uninstall": ("Деинсталиране", "Deinstallation", "Odinstalowanie"),
    "Delete all tabs and settings of this plugin when it is deleted from the Plugins screen": ("Изтрий всички раздели и настройки на това разширение, когато бъде изтрито от екрана Разширения", "Alle Tabs und Einstellungen dieses Plugins löschen, wenn es auf der Plugin-Seite gelöscht wird", "Usuń wszystkie zakładki i ustawienia tej wtyczki, gdy zostanie usunięta z ekranu Wtyczki"),
    "Off by default, so deleting and reinstalling the plugin keeps your tabs. YIKES data is never touched.": ("Изключено по подразбиране, така че изтриването и повторното инсталиране запазва разделите. Данните на YIKES никога не се променят.", "Standardmäßig aus, damit Ihre Tabs beim Löschen und Neuinstallieren erhalten bleiben. YIKES-Daten werden nie angefasst.", "Domyślnie wyłączone, więc usunięcie i ponowna instalacja zachowa zakładki. Dane YIKES nigdy nie są zmieniane."),
    "Copies the tabs of \"Custom Product Tabs for WooCommerce\" by YIKES: every product's tabs (in the same order, with the same content) and the saved tabs, which become global tabs. Products that used a saved tab are linked to the new global tab, so editing it updates them all. Saved tabs that the YIKES Pro add-on assigned to all products or to categories/tags keep those rules.": (
        "Копира разделите на „Custom Product Tabs for WooCommerce“ от YIKES: разделите на всеки продукт (в същия ред и със същото съдържание) и запазените раздели, които стават глобални. Продуктите, използвали запазен раздел, се свързват с новия глобален раздел, така че редакцията му обновява всички. Запазените раздели, които YIKES Pro е задал за всички продукти или за категории/етикети, запазват тези правила.",
        "Kopiert die Tabs von „Custom Product Tabs for WooCommerce“ von YIKES: die Tabs jedes Produkts (in derselben Reihenfolge, mit demselben Inhalt) und die gespeicherten Tabs, die zu globalen Tabs werden. Produkte, die einen gespeicherten Tab verwendet haben, werden mit dem neuen globalen Tab verknüpft, sodass eine Änderung alle aktualisiert. Gespeicherte Tabs, die das YIKES-Pro-Add-on allen Produkten oder Kategorien/Schlagwörtern zugewiesen hat, behalten diese Regeln.",
        "Kopiuje zakładki „Custom Product Tabs for WooCommerce” od YIKES: zakładki każdego produktu (w tej samej kolejności, z tą samą treścią) oraz zapisane zakładki, które stają się globalne. Produkty, które używały zapisanej zakładki, zostają z nią powiązane, więc jej edycja aktualizuje wszystkie. Zapisane zakładki, które dodatek YIKES Pro przypisał do wszystkich produktów lub do kategorii/tagów, zachowują te reguły.",
    ),
    "YIKES data is only read. Nothing is changed or deleted, and YIKES keeps working until you deactivate it.": ("Данните на YIKES само се четат. Нищо не се променя или изтрива и YIKES продължава да работи, докато не го деактивирате.", "YIKES-Daten werden nur gelesen. Nichts wird geändert oder gelöscht, und YIKES funktioniert weiter, bis Sie es deaktivieren.", "Dane YIKES są tylko odczytywane. Nic nie jest zmieniane ani usuwane, a YIKES działa, dopóki go nie dezaktywujesz."),
    "Running it again is safe: tabs are never duplicated, and products whose YIKES tabs did not change are skipped.": ("Повторното изпълнение е безопасно: разделите не се дублират, а продуктите без промени в YIKES се пропускат.", "Ein erneuter Lauf ist sicher: Tabs werden nie doppelt angelegt, und Produkte mit unveränderten YIKES-Tabs werden übersprungen.", "Ponowne uruchomienie jest bezpieczne: zakładki nigdy się nie dublują, a produkty bez zmian w YIKES są pomijane."),
    "Tabs YIKES never showed (no title, or hidden by another tab with the same title) are imported switched off and listed below.": ("Разделите, които YIKES никога не е показвал (без заглавие или скрити от друг раздел със същото заглавие), се импортират изключени и са изброени по-долу.", "Tabs, die YIKES nie angezeigt hat (ohne Titel oder durch einen anderen Tab mit gleichem Titel verdeckt), werden deaktiviert importiert und unten aufgelistet.", "Zakładki, których YIKES nigdy nie pokazywał (bez tytułu lub ukryte przez inną zakładkę o tym samym tytule), są importowane jako wyłączone i wymienione poniżej."),
    "Large catalogues are handled in batches of 100 products. Command line: wp pnscripts-product-tabs import-yikes --dry-run": ("Големите каталози се обработват на партиди по 100 продукта. Команден ред: wp pnscripts-product-tabs import-yikes --dry-run", "Große Kataloge werden in Paketen zu 100 Produkten verarbeitet. Kommandozeile: wp pnscripts-product-tabs import-yikes --dry-run", "Duże katalogi są przetwarzane partiami po 100 produktów. Wiersz poleceń: wp pnscripts-product-tabs import-yikes --dry-run"),
    "Found:": ("Намерени:", "Gefunden:", "Znaleziono:"),
    "YIKES plugin active": ("разширението YIKES е активно", "YIKES-Plugin aktiv", "wtyczka YIKES aktywna"),
    "YIKES plugin not active (data can still be imported)": ("разширението YIKES не е активно (данните пак могат да се импортират)", "YIKES-Plugin nicht aktiv (Daten können trotzdem importiert werden)", "wtyczka YIKES nieaktywna (dane nadal można zaimportować)"),
    "last import: %s": ("последен импорт: %s", "letzter Import: %s", "ostatni import: %s"),
    "Dry run (change nothing)": ("Пробно изпълнение (без промени)", "Probelauf (nichts ändern)", "Próba (bez zmian)"),
    "Import now": ("Импортирай сега", "Jetzt importieren", "Importuj teraz"),
    "Undo import": ("Отмени импорта", "Import rückgängig machen", "Cofnij import"),
    "Notes": ("Бележки", "Hinweise", "Uwagi"),
    "PN Product Tabs found tabs from Custom Product Tabs for WooCommerce (YIKES). Import them in one click; YIKES data stays untouched.": ("PN Product Tabs откри раздели от Custom Product Tabs for WooCommerce (YIKES). Импортирайте ги с едно кликване; данните на YIKES остават непокътнати.", "PN Product Tabs hat Tabs aus Custom Product Tabs for WooCommerce (YIKES) gefunden. Importieren Sie sie mit einem Klick; die YIKES-Daten bleiben unverändert.", "PN Product Tabs znalazł zakładki z Custom Product Tabs for WooCommerce (YIKES). Zaimportuj je jednym kliknięciem; dane YIKES pozostaną nietknięte."),
    "Review the import": ("Прегледай импорта", "Import prüfen", "Sprawdź import"),
    "Dismiss": ("Скрий", "Ausblenden", "Odrzuć"),
    "Saved tab #%d has no title and was skipped.": ("Запазеният раздел №%d няма заглавие и е пропуснат.", "Gespeicherter Tab #%d hat keinen Titel und wurde übersprungen.", "Zapisana zakładka #%d nie ma tytułu i została pominięta."),
    "Saved tab \"%1$s\" was assigned by the taxonomy \"%2$s\", which is not supported; it is imported for the categories and tags only.": ("Запазеният раздел „%1$s“ е бил зададен по таксономията „%2$s“, която не се поддържа; импортира се само за категориите и етикетите.", "Der gespeicherte Tab „%1$s“ wurde über die Taxonomie „%2$s“ zugewiesen, die nicht unterstützt wird; er wird nur für die Kategorien und Schlagwörter importiert.", "Zapisana zakładka „%1$s” była przypisana według taksonomii „%2$s”, która nie jest obsługiwana; importowana jest tylko dla kategorii i tagów."),
    "Product #%d: a tab without a title (never shown by YIKES) was imported switched off.": ("Продукт №%d: раздел без заглавие (никога не показван от YIKES) е импортиран изключен.", "Produkt #%d: Ein Tab ohne Titel (von YIKES nie angezeigt) wurde deaktiviert importiert.", "Produkt #%d: zakładka bez tytułu (nigdy niepokazywana przez YIKES) została zaimportowana jako wyłączona."),
    "Product #%1$d: the tab \"%2$s\" was hidden by a later tab with the same title in YIKES; it was imported switched off.": ("Продукт №%1$d: разделът „%2$s“ е бил скрит от по-късен раздел със същото заглавие в YIKES; импортиран е изключен.", "Produkt #%1$d: Der Tab „%2$s“ wurde in YIKES durch einen späteren Tab mit gleichem Titel verdeckt; er wurde deaktiviert importiert.", "Produkt #%1$d: zakładka „%2$s” była ukryta przez późniejszą zakładkę o tym samym tytule w YIKES; zaimportowano ją jako wyłączoną."),
    "PN Product Tabs needs WooCommerce %s or newer to be active.": ("PN Product Tabs изисква активен WooCommerce %s или по-нов.", "PN Product Tabs benötigt ein aktives WooCommerce %s oder neuer.", "PN Product Tabs wymaga aktywnego WooCommerce w wersji %s lub nowszej."),
    "Sorry, you are not allowed to view global product tabs.": ("Нямате права да преглеждате глобалните продуктови раздели.", "Sie dürfen globale Produkt-Tabs nicht ansehen.", "Nie masz uprawnień do przeglądania globalnych zakładek produktów."),
    "Global product tabs": ("Глобални продуктови раздели", "Globale Produkt-Tabs", "Globalne zakładki produktów"),
    "Global product tab": ("Глобален продуктов раздел", "Globaler Produkt-Tab", "Globalna zakładka produktu"),
    "Product tabs": ("Продуктови раздели", "Produkt-Tabs", "Zakładki produktów"),
    "Add global tab": ("Добави глобален раздел", "Globalen Tab hinzufügen", "Dodaj globalną zakładkę"),
    "New global tab": ("Нов глобален раздел", "Neuer globaler Tab", "Nowa globalna zakładka"),
    "Search global tabs": ("Търси в глобалните раздели", "Globale Tabs durchsuchen", "Szukaj globalnych zakładek"),
    "No global tabs yet.": ("Още няма глобални раздели.", "Noch keine globalen Tabs.", "Brak globalnych zakładek."),
    "No global tabs in the trash.": ("Няма глобални раздели в кошчето.", "Keine globalen Tabs im Papierkorb.", "Brak globalnych zakładek w koszu."),
    "Global tab updated.": ("Глобалният раздел е обновен.", "Globaler Tab aktualisiert.", "Globalna zakładka zaktualizowana."),
    "Global tab published.": ("Глобалният раздел е публикуван.", "Globaler Tab veröffentlicht.", "Globalna zakładka opublikowana."),
    "Reusable tabs shown on all products, on products in chosen categories or tags, or where added manually.": ("Раздели за многократна употреба, показвани на всички продукти, на продукти в избрани категории или етикети, или там, където са добавени ръчно.", "Wiederverwendbare Tabs für alle Produkte, für Produkte in gewählten Kategorien oder Schlagwörtern oder dort, wo sie manuell hinzugefügt wurden.", "Zakładki wielokrotnego użytku wyświetlane na wszystkich produktach, w wybranych kategoriach lub tagach albo tam, gdzie dodano je ręcznie."),
    "https://pnscripts.com/marketplace/pn-product-tabs": ("https://pnscripts.com/marketplace/pn-product-tabs",) * 3,
    "https://pnscripts.com": ("https://pnscripts.com",) * 3,
}

PLURALS = {
    "%d product with YIKES tabs": (
        ("%d продукт с раздели от YIKES", "%d продукта с раздели от YIKES"),
        ("%d Produkt mit YIKES-Tabs", "%d Produkte mit YIKES-Tabs"),
        ("%d produkt z zakładkami YIKES", "%d produkty z zakładkami YIKES", "%d produktów z zakładkami YIKES"),
    ),
}

LOCALES = {
    "bg_BG": (0, "Bulgarian", "nplurals=2; plural=(n != 1);"),
    "de_DE": (1, "German", "nplurals=2; plural=(n != 1);"),
    "pl_PL": (2, "Polish", "nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);"),
}


def esc(s):
    return s.replace("\\", "\\\\").replace('"', '\\"')


def unq(block):
    return "".join(re.findall(r'"((?:[^"\\]|\\.)*)"', block)).replace('\\"', '"').replace("\\\\", "\\")


def main(root):
    pot = (root / "languages" / "pnscripts-product-tabs.pot").read_text(encoding="utf-8")
    header, _, body = pot.partition("\n\n")
    entries = body.split("\n\n")
    for locale, (i, name, plural_forms) in LOCALES.items():
        out = []
        h = header
        h = h.replace('"Language-Team: LANGUAGE <LL@li.org>\\n"', f'"Language-Team: {name}\\n"')
        h = re.sub(r'"Language: [^"]*"\n', "", h)
        h = re.sub(r'"PO-Revision-Date: [^"]*"', lambda m: '"PO-Revision-Date: 2026-10-04 00:00+0000\\n"', h)
        h = re.sub(r'"Last-Translator: [^"]*"', lambda m: '"Last-Translator: PN Scripts <https://pnscripts.com>\\n"', h)
        h = h.rstrip() + f'\n"Language: {locale}\\n"\n"Plural-Forms: {plural_forms}\\n"'
        out.append(h)
        missing = 0
        for e in entries:
            if not e.strip():
                continue
            m = re.search(r'^msgid ((?:".*"\n?)+)', e, re.M)
            if not m:
                continue
            msgid = unq(m.group(1))
            if "msgid_plural" in e:
                forms = PLURALS.get(msgid)
                if not forms:
                    missing += 1
                    out.append(e)
                    continue
                e = re.sub(r'msgstr\[\d+\] ""\n?', "", e).rstrip()
                for k, form in enumerate(forms[i]):
                    e += f'\nmsgstr[{k}] "{esc(form)}"'
                out.append(e)
                continue
            tr = T.get(msgid)
            if tr is None:
                missing += 1
                out.append(e)
                continue
            e = re.sub(r'msgstr ""\s*$', f'msgstr "{esc(tr[i])}"', e.rstrip())
            out.append(e)
        (root / "languages" / f"pnscripts-product-tabs-{locale}.po").write_text("\n\n".join(out) + "\n", encoding="utf-8")
        print(f"{locale}: {missing} untranslated")


if __name__ == "__main__":
    main(Path(sys.argv[1] if len(sys.argv) > 1 else "."))
