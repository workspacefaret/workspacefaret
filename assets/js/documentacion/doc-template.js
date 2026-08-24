(function () {
    var main = document.querySelector('main.doc-main');
    var toc = document.querySelector('nav.doc-toc');
    if (!main || !toc) return;

    var sections = main.querySelectorAll(':scope > section');
    var list = toc.querySelector('ol');
    if (!list || sections.length === 0) {
        toc.hidden = true;
        return;
    }

    var usados = {};

    var MAPA_ACENTOS = {
        'a': 'áàäâ', 'e': 'éèëê', 'i': 'íìïî',
        'o': 'óòöô', 'u': 'úùüû', 'n': 'ñ', 'c': 'ç'
    };

    function quitarAcentos(texto) {
        var resultado = texto;
        Object.keys(MAPA_ACENTOS).forEach(function (base) {
            MAPA_ACENTOS[base].split('').forEach(function (acentuada) {
                resultado = resultado.split(acentuada).join(base);
            });
        });
        return resultado;
    }

    function slug(texto) {
        var base = quitarAcentos(texto.toLowerCase())
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '') || 'seccion';

        var id = base;
        var i = 2;
        while (usados[id]) {
            id = base + '-' + i;
            i++;
        }
        usados[id] = true;
        return id;
    }

    sections.forEach(function (section) {
        var heading = section.querySelector(':scope > h2');
        if (!heading) return;

        if (!section.id) {
            section.id = slug(heading.textContent.trim());
        }

        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = '#' + section.id;
        a.textContent = heading.textContent.trim();
        li.appendChild(a);
        list.appendChild(li);
    });
})();
