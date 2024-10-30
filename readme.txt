=== Integration for Billingo & WooCommerce ===
Contributors: passatgt
Tags: billingo.hu, billingo, woocommerce, szamlazas, magyar
Requires at least: 3.5
Tested up to: 4.7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Nem hivatalos Billingo összeköttetés WooCommerce-hez.

== Description ==

= Funkciók =
*   Manuális számlakészítés - minden rendelésnél a jobb oldalon megjelenik egy új gomb, rákattintáskor elküldi az adatokat a Billingo-nak és legenerálja a számlát.
*   Automata számlakészítés - Ha a rendelés állapota átállítódik befejezettre, automatán legenerálja a számlát
*   Díjbekérő készítés - Ha a rendelés állapota átállítódik függőben lévőre, automatán legenerálja a díjbekérő számlát. Lehet kézzel egy-egy rendeléshez külön díjbekérőt is csinálni.
*   Számlaértesítő - A számlákat a Billingo rendszere automatikusan elküldi emailben a vásárlónak
*   A generált számla letölthető a WooCommerce rendeléskezelőjéből is
*   Minden számlakészítésnél létrehoz egy megjegyzést a rendeléshoz, hogy mikor, milyen néven készült el a számla
*   Fizetési határidő és megjegyzés írható a számlákhoz
*   Kuponokkal is működik, a számlán negatív tételként fog megjelenni a végén
*   Szállítást is ráírja a számlára
*   A PDF fájl letölthető egyből a Rendelések oldalról is(táblázat utolsó oszlopa)

= Használat =
Telepítés után a WooCommerce / Beállítások oldalon meg kell adni a Billingo API kulcsokat(billingo-ra belépve a beállításokban találod), illetve az ott található többi beállításokat igény szerint. Ha megadtad a kulcsokat, nyomj rá a mentésre, utána megjelenik a fizetési módok összepárosítása rész, ahol ki kell választanod, hogy a beállított fizetési módjaidnak mi a Billingo megfelelője.
Minden rendelésnél jobb oldalon megjelenik egy új doboz, ahol egy gombnyomással létre lehet hozni a számlát. Az Opciók gombbal felül lehet írni a beállításokban megadott értékeket 1-1 számlához.
Ha az automata számlakészítés be van kapcsolva, akkor a rendelés lezárásakor(Teljesített rendelés státuszra állítás) automatikusan létrehozza a számlát a rendszer.
A számlakészítés kikapcsolható 1-1 rendelésnél az Opciók legördülőn belül.
Az elkészült számla a rendelés aloldalán és a rendelés listában az utolsó oszlopban található PDF ikonra kattintva letölthető.

FONTOS: Felelősséget én nem vállalok a készített számlákért, mindenki ellenőrizze le saját magának, hogy minden jól működik e. Sajnos minden esetet nem tudok tesztelni, különböző áfakulcsok, termékvariációk, kuponok stb..., így mindenkéne tesztelje le éles használat előtt, ha valami gond van, jelezze felém és megpróbálom javítani. Ez nem egy hivatalos Billingo bővítmény!

Az számla tételek és információk generálás előtt módosíthatók a `wc_billingo_clientdata` és `wc_billingo_invoicedata` filterekkel. Előbbi az ügyféladatokat módosítja, utóbbi a számlán lévő tételeket. Ez minden esetben az éppen aktív téma functions.php fájlban történjen, hogy az esetleges plugin frissítés ne törölje ki a módosításokat! Például:

    <?php
    //Számlanyelv változtatás
    add_filter('wc_billingo_invoicedata','wc_billingo_lang',10,2);
    function wc_billingo_lang($data,$order) {
        $data['template_lang_code'] = 'en';
        return $data;
    }
    ?>

== Installation ==

1. Töltsd le a bővítményt vagy telepítsd bel a Bővítmények menüpontban
2. WooCommerce / Beállítások oldal alján megjelennek a Billingo beállítások, ezeket be kell állítani
3. Beállíátsok elmentése után lehetőség van a fizetősi módok összepárosítására a billingo rendszerében megfelelővel
3. Működik(ha minden jól megy)

== Screenshots ==

1. Beállítások képernyő(WooCommerce / Beállítások)
2. Számlakészítés doboz a rendelés oldalon

== Changelog ==

= 1.1 =
* WooCommerce 3.0 kompatibilitás

= 1.0.4 =
* Megadható az adószám, 100e ft feletti áfatartalomnál a vásárlás oldalon megjelenik az adószám mező(opcionális) és egy figyelmeztetés, hogy kötelező megadni, ha van.
* Ha nem sikerült létrehozni számlát, akkor a rendelés megjegyzéseibe bekerül, hogy mi volt a hiba

= 1.0.3 =
* Ingyenes szállítás javítása, forrás: https://www.facebook.com/groups/wpcsoport/permalink/1415346541816505/

= 1.0.1 =
* Számlatömb ID megadható a beállításokban

= 1.0 =
* WordPress.org-ra feltöltött plugin első verziója
