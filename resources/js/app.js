import './bootstrap';
//console.log('connected!');
//my scripts++++++
function menuNav() {
    var showClass = 'blok show w-full block flex-grow lg:flex lg:items-center lg:w-auto';
    var hiddenClass = 'blok hidden w-full block flex-grow lg:flex lg:items-center lg:w-auto';
    //document.getElementById('id_menu').className==show?document.getElementById('id_menu').className=hidden:document.getElementById('id_menu').className=show;


    var menu = document.getElementById("id_menu");
    if (menu.className === showClass) {
        menu.className = hiddenClass;
    } else {
        menu.className = showClass;
    }
}