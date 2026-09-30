<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="Landing del buzón ciudadano de preocupaciones de la Cátedra Ceuta-IATEB">
<title>Ceuta Me Preocupa</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('paloma2.svg') }}">
<link rel="stylesheet" href="{{ asset('styles.css') }}">

</head>
<body>
<header class="nav">
  <div class="wrap">
    <div class="nav-brand">
      <img src="{{ asset('logo-nav.webp') }}" alt="Cátedra Ceuta-IATEB" />
      <span>Ceuta Me Preocupa</span>
    </div>
    <a class="btn btn-accent btn-sm" href="#formulario">Enviar mi preocupación</a>
  </div>
</header>

<main>
  <section class="hero">
    <svg class="hero-wing" viewBox="965 245 1071 756" preserveAspectRatio="xMidYMid meet" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
<path transform="translate(0,0)" fill="rgb(254,254,254)" d="M 0 0 L 2048 0 L 2048 1890 L 2041.15 1890 L 2042.78 605.548 L 2042.78 207.57 L 2042.79 77.615 C 2042.8 54.6195 2043.5 26.6419 2042.34 3.97041 C 2015.32 2.91813 1981.76 3.78582 1954.4 3.78547 L 1783.56 3.78637 L 1268.02 3.79915 L 941.318 3.80587 L 841.604 3.74974 C 828.233 3.73878 801.64 2.99917 788.953 4.25303 C 783.355 5.95575 682.232 5.92454 674.942 4.3471 C 669.89 3.4073 651.119 3.73311 645.204 3.7414 L 586.473 3.82969 L 486.542 3.7638 C 470.071 3.75206 446.871 3.04762 430.907 4.2048 C 425.119 5.99301 323.057 5.96293 316.914 4.00118 C 312.422 2.6041 266.421 3.16092 258.747 3.14003 L 74.5639 3.23742 L 29.6595 3.13988 C 22.1325 3.10555 9.21668 2.64343 2.11544 3.62055 L 2.02876 60.0732 L 2.1019 298.663 L 2.11271 378.166 C 2.11779 390.366 2.60968 406.918 2.05256 418.837 L 2.00962 555.893 C 2.16368 609.126 2.78022 663.765 2.0805 716.88 C 1.91207 782.624 0.93386 852.049 2.08617 917.549 C 2.74511 953.226 1.68345 989.144 2.09253 1024.85 C 2.1596 1030.7 2.25186 1036.99 2.03967 1042.8 L 2.56008 1890 L 0 1890 L 0 0 z"/>
<path transform="translate(0,0)" fill="rgb(183,191,208)" d="M 1140.18 709.366 C 1141.22 586.859 1295.76 462.993 1387.45 398.793 C 1469.43 341.395 1561.91 279.217 1660.3 256.332 C 1697.6 247.657 1734.95 246.994 1772.94 245.228 C 1816.01 242.745 1851.32 239.211 1894.49 244.405 C 1876.42 256.139 1856.47 266.883 1838.94 279.697 C 1741.61 350.83 1651.57 434.391 1570.2 523.249 C 1566.46 527.342 1554.88 538.574 1554.53 543.134 L 1558.41 543.697 C 1581.19 541.139 1604.9 541.288 1627.67 540.606 C 1703.2 538.345 1779.53 541.538 1850.6 569.576 C 1880.24 581.272 1902.24 594.42 1930.42 608.666 C 1967 627.155 2003.12 643.543 2035.51 669.233 C 1969.85 668.234 1901.74 678.306 1837.5 690.968 C 1768.88 704.49 1698.49 720.162 1631.68 740.959 C 1561.64 763.259 1492.94 789.564 1425.92 819.743 C 1409.52 826.959 1393.57 832.659 1376.77 840.4 C 1312.45 864.822 1220.16 894.084 1164.65 835.545 C 1144.07 813.837 1133.47 773.482 1133.7 744.179 C 1134.02 734.728 1138 718.973 1140.18 709.366 z"/>
<path transform="translate(0,0)" fill="rgb(134,170,237)" d="M 1140.18 709.366 C 1141.22 586.859 1295.76 462.993 1387.45 398.793 C 1469.43 341.395 1561.91 279.217 1660.3 256.332 C 1697.6 247.657 1734.95 246.994 1772.94 245.228 C 1816.01 242.745 1851.32 239.211 1894.49 244.405 C 1876.42 256.139 1856.47 266.883 1838.94 279.697 C 1741.61 350.83 1651.57 434.391 1570.2 523.249 C 1566.46 527.342 1554.88 538.574 1554.53 543.134 L 1558.41 543.697 C 1532.62 548.467 1498.92 547.072 1471.24 551.28 C 1364.31 567.529 1233.24 588.197 1159.51 675.328 C 1154.25 681.825 1142.2 702.014 1140.18 709.366 z"/>
<path transform="translate(0,0)" fill="rgb(134,170,237)" d="M 965.033 663.741 C 985.362 669.213 1004.99 676.303 1023.36 685.091 C 1042.38 694.192 1118.55 742.435 1133.7 744.179 C 1133.47 773.482 1144.07 813.837 1164.65 835.545 C 1220.16 894.084 1312.45 864.822 1376.77 840.4 C 1380.64 842.818 1394.21 846.56 1399.29 848.223 C 1416.24 853.712 1433.15 859.341 1450.01 865.109 L 1564.11 904.043 C 1615.17 921.781 1664.6 939.385 1717.09 953.027 C 1743 959.76 1768.9 971.22 1795.25 976.612 C 1801.12 977.814 1815.9 973.154 1822.07 971.484 L 1822.89 985.595 C 1806.71 980.195 1796.13 980.102 1779.4 983.651 C 1709.16 998.547 1645.99 1001.43 1574.17 1000.85 C 1503.12 1000.63 1432.13 996.82 1361.47 989.439 C 1303.52 983.783 1244.1 980.364 1190.63 953.522 C 1123.65 919.899 1075.46 864.375 1045.61 796.373 C 1028.36 757.072 997.248 691.288 965.033 663.741 z"/>
<path transform="translate(0,0)" fill="rgb(46,80,162)" d="M 999.158 680.572 C 1000.69 679.601 1002.65 679.582 1004.2 680.525 C 1005.75 681.467 1006.64 683.207 1006.49 685.018 C 1006.34 686.828 1005.17 688.397 1003.48 689.067 C 1001.19 689.978 998.572 688.992 997.451 686.789 C 996.33 684.587 997.069 681.893 999.158 680.572 z"/>

</svg>
    <div class="hero-flag" aria-hidden="true">
      <img src="{{ asset('Flag_of_Ceuta.svg.webp') }}" alt="">
      <span>Ceuta, ciudad que escucha</span>
    </div>
    <div class="wrap">
      <div class="hero-inner">
        <span class="eyebrow">Buzón ciudadano · Cátedra Ceuta-IATEB</span>
        <h1>Ceuta me preocupa</h1>
        <p class="lead">En el contexto de emergencia que atraviesa la ciudad, la Cátedra Ceuta-IATEB abre este canal para que cuentes, con tus propias palabras, qué preocupaciones, incidencias no urgentes o propuestas de mejora tienes sobre la gestión pública de Ceuta. Tu respuesta se suma a las demás para construir un retrato agregado y verificable de lo que preocupa a la ciudad.</p>
        <div class="hero-actions">
          <a class="btn btn-accent" href="#formulario">Contar mi preocupación →</a>
          <span class="hero-note">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L4 5V11C4 16.5 7.4 21.2 12 22C16.6 21.2 20 16.5 20 11V5L12 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
            Cuestionario anónimo · unos 5 minutos
          </span>
        </div>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="notice">
        <div class="notice-head">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v4M12 16.5h.01M10.3 3.9L2.7 17.5c-.5.9.1 2 1.1 2h16.4c1 0 1.6-1.1 1.1-2L13.7 3.9c-.5-.9-1.8-.9-2.3 0z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <h3>Antes de enviar tu preocupación</h3>
        </div>
        <ul>
          <li>Una sugerencia no equivale a una reclamación administrativa.</li>
          <li>El envío no abre ningún expediente ni garantiza que la medida se adopte.</li>
          <li>Si tu situación es urgente, dirígete directamente al servicio competente (emergencias, Policía Local, tu organismo gestor): este buzón no sustituye esos canales.</li>
        </ul>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="split">
        <div class="section-head" style="margin-bottom:0;">
          <span class="kicker">Qué es</span>
          <h2>Un canal de escucha, no un registro de quejas</h2>
        </div>
        <div>
          <p class="lede">«Ceuta Me Preocupa» es un buzón impulsado por la Cátedra Ceuta-IATEB para recoger, con tus propias palabras, preocupaciones, incidencias no urgentes y propuestas de mejora relacionadas con la gestión pública de Ceuta.</p>
          <p class="lede">No es un registro de quejas formales, ni un canal de emergencias, ni una vía de denuncia, ni un procedimiento para solicitar prestaciones individualizadas. Su objetivo es identificar patrones, proponer a qué organismo corresponde previsiblemente cada asunto, y devolver a la ciudadanía una respuesta agregada y verificable.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="band">
    <div class="wrap">
      <div class="section-head">
        <span class="kicker">Qué puedes contarnos</span>
        <h2>Nueve áreas de la vida en Ceuta</h2>
        <p class="lede">Elige la categoría que mejor describa tu preocupación</p>
      </div>
      <div class="cat-grid">
        <div class="cat"><h3>Urbanismo</h3><p>Planeamiento, licencias, vivienda, obras, accesibilidad urbana, patrimonio.</p></div>
        <div class="cat"><h3>Movilidad</h3><p>Transporte público, tráfico, aparcamiento, itinerarios peatonales, carga y descarga.</p></div>
        <div class="cat"><h3>Educación no universitaria</h3><p>Centros, escolarización, profesorado, currículo, formación profesional, convivencia escolar.</p></div>
        <div class="cat"><h3>Universidad</h3><p>Docencia, investigación, becas, instalaciones, vida universitaria.</p></div>
        <div class="cat"><h3>Economía</h3><p>Empleo, comercio, emprendimiento, turismo, ayudas, transformación económica.</p></div>
        <div class="cat"><h3>Sanidad</h3><p>Salud pública, atención sanitaria, accesibilidad, prevención.</p></div>
        <div class="cat"><h3>Seguridad</h3><p>Seguridad ciudadana, seguridad de instalaciones, policía local, prevención.</p></div>
        <div class="cat"><h3>Servicios Sociales</h3><p>Atención social, dependencia, inclusión, vulnerabilidad, igualdad, apoyo comunitario.</p></div>
        <div class="cat"><h3>Medioambiente</h3><p>Residuos, limpieza, ruido, zonas verdes, contaminación, sostenibilidad.</p></div>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="section-head">
        <span class="kicker">Cómo funciona</span>
        <h2>Tres pasos, sin dato de más</h2>
      </div>
      <ol class="steps">
        <li class="step">
          <span class="step-num">01</span>
          <div>
            <h3>Cuéntanos, sin datos identificativos</h3>
            <p>Eliges categoría, describes brevemente el problema o la propuesta (máximo recomendado: 1.000 caracteres) y, si quieres, el resultado esperado. No se pide DNI, dirección, correo, teléfono ni nombre; solo si deseas respuesta individual puedes dejar un contacto en un campo aparte.</p>
          </div>
        </li>
        <li class="step">
          <span class="step-num">02</span>
          <div>
            <h3>Un equipo humano revisa y clasifica</h3>
            <p>Tu respuesta se seudonimiza —identificador aleatorio, sin tus datos— y una persona revisora confirma la categoría y propone el organismo competente, marcando «competencia por confirmar» cuando hay dudas.</p>
          </div>
        </li>
        <li class="step">
          <span class="step-num">03</span>
          <div>
            <h3>Publicamos resultados, no relatos individuales</h3>
            <p>Cada mes se hacen públicos los recuentos por categoría, los principales temas, los organismos a los que se ha derivado cada asunto y las acciones en curso. Nunca se difunden los textos originales.</p>
          </div>
        </li>
      </ol>
    </div>
  </section>

  <section class="band">
    <div class="wrap">
      <div class="section-head">
        <span class="kicker">Privacidad</span>
        <h2>Tu anonimato, por diseño</h2>
        <p class="lede">El formulario está pensado para minimizar los datos desde el primer campo:</p>
      </div>
      <div class="card-row cols-3">
        <div class="card">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 15a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="1.6"/><path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6z" stroke="currentColor" stroke-width="1.6"/></svg>
          <h3>Sin datos identificativos por defecto</h3>
          <p>No se pide DNI, dirección postal, correo, teléfono, nombre ni fecha de nacimiento. El envío es anónimo salvo que tú decidas dejar un contacto aparte para recibir respuesta.</p>
        </div>
        <div class="card">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4M12 3l7 3v6c0 4.5-3 8.5-7 9-4-.5-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
          <h3>Seudonimización, no solo borrado de nombres</h3>
          <p>Quitar automáticamente nombres o correos del texto no basta para anonimizar. El sistema detecta patrones de datos personales y los sustituye, y una persona revisa una muestra antes de compartir cualquier resultado.</p>
        </div>
        <div class="card">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7l8-4 8 4v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
          <h3>Acceso por niveles</h3>
          <p>Tu respuesta original solo la ve el equipo mínimo autorizado. El resto del proceso trabaja con un registro seudonimizado, y lo que se publica es siempre agregado: recuentos y tendencias, nunca tu texto.</p>
        </div>
      </div>
    </div>
  </section>
  <section class="band">
    <div class="wrap">
      <div class="section-head">
        <span class="kicker">Quién impulsa esto</span>
        <h2>La Cátedra Ceuta-IATEB</h2>
        <p class="lede">La Cátedra UGR - Ciudad Autónoma de Ceuta en Inteligencia Artificial y Tecnologías Emergentes
          para el Bienestar (Cátedra CEUTA-IATEB) es una iniciativa conjunta entre la Universidad de Granada y la Ciudad
          Autónoma de Ceuta. Su objetivo es convertirse en un núcleo relevante para la investigación, la formación y la
          transferencia de conocimiento en el ámbito de la Inteligencia Artificial y las Tecnologías Emergentes aplicadas a la
          Educación, la Salud, los Servicios Sociales y la Economía.</p>
      </div>
      <div class="partners">
        <span class="partner-tag">Universidad de Granada</span>
        <span class="partner-tag">Ciudad Autónoma de Ceuta</span>
      </div>
      <a class="btn btn-ghost" style="margin-top:28px;" href="https://catedras.ugr.es/iateb/" target="_blank" rel="noopener">Conoce la Cátedra Ceuta-IATEB →</a>
    </div>
  </section>

  <section id="formulario">
    <div class="wrap">
      <div class="section-head">
        <span class="kicker">Formulario</span>
        <h2>Cuéntanos tu preocupación</h2>
        <p class="lede">Rellena solo lo que consideres necesario. No pedimos datos identificativos: el envío es anónimo salvo que dejes un contacto opcional al final.</p>
      </div>
@if (session('exito'))
      <div class="notice" role="status" style="margin-bottom:24px;">
        <div class="notice-head"><h3>Enviado correctamente</h3></div>
        <p style="margin:0;color:var(--ink-soft);">{{ session('exito') }}</p>
      </div>
      @endif

      <form class="buzon-form" method="post" action="{{ route('sugerencias.store') }}" novalidate>
        @csrf
        <div style="position:absolute; left:-9999px;" aria-hidden="true">
            <label for="website">No rellenes este campo</label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
        </div>
        <input type="hidden" name="_t" value="{{ encrypt(now()->timestamp) }}">
        <div class="form-grid">
          <div class="field">
            <label for="categoria_id">Categoría principal <span class="req">*</span></label>
            <select id="categoria_id" name="categoria_id" required>
              <option value="" disabled {{ old('categoria_id') ? '' : 'selected' }}>Selecciona una categoría</option>
              @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected(old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
              @endforeach
            </select>
            @error('categoria_id') <span class="form-error">{{ $message }}</span> @enderror
          </div>
          <div class="field">
            <label>Subcategoría <span class="req">*</span> <span class="form-note">(elige 1 o 2)</span></label>
            <div id="subcategorias-cont" class="subcat-group">
              <p class="form-note" style="margin:0;">Elige primero una categoría.</p>
            </div>
            @error('subcategorias') <span class="form-error">{{ $message }}</span> @enderror
            @error('subcategorias.0') <span class="form-error">{{ $message }}</span> @enderror
          </div>
        </div>

        <div class="field">
          <label for="zona">Zona aproximada o barrio</label>
          <input type="text" id="zona" name="zona" placeholder="Ej.: Príncipe Alfonso, centro…" value="{{ old('zona') }}" @disabled(!old('zona'))>
          <label class="checkbox-line">
            <input type="checkbox" id="zona-omitir" @checked(!old('zona'))>
            No deseo indicarlo
          </label>
          @error('zona') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="descripcion">¿Qué problema observa o qué propuesta plantea? <span class="req">*</span></label>
          <textarea id="descripcion" name="descripcion" rows="5" maxlength="1000" required placeholder="Describe brevemente tu preocupación o propuesta (máximo 1.000 caracteres)">{{ old('descripcion') }}</textarea>
          @error('descripcion') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="resultado_esperado">Resultado esperado o propuesta de mejora (opcional)</label>
          <textarea id="resultado_esperado" name="resultado_esperado" rows="3" maxlength="1000" placeholder="¿Qué te gustaría que ocurriera?">{{ old('resultado_esperado') }}</textarea>
          @error('resultado_esperado') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="field field-contact">
          <label for="contacto">Contacto opcional (solo si quieres respuesta individual)</label>
          <input type="text" id="contacto" name="contacto" placeholder="Correo o teléfono (opcional)" value="{{ old('contacto') }}">
          @error('contacto') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label class="checkbox-line">
            <input type="checkbox" name="acepta_privacidad" value="1" @checked(old('acepta_privacidad'))>
            <p style="margin: 0">He leído y acepto la <a href="{{ route('privacidad') }}" target="_self">información de privacidad</a> y los límites del buzón. <span class="req">*</span></p>
          </label>
          @error('acepta_privacidad') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-actions">
          <button class="btn btn-accent">Enviar mi preocupación</button>
        </div>
      </form>
    </div>
  </section>

  <section class="cta-final">
    <div class="wrap">
      <div>
        <h2>Tu preocupación cuenta.</h2>
        <p>Cinco minutos tuyos hoy pueden formar parte del retrato de Ceuta mañana.</p>
      </div>
      <a class="btn btn-accent" href="#formulario">Ir al formulario →</a>
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div class="footer-row">
      <div class="footer-brand">
        <img src="{{ asset('logo-footer.webp') }}" alt="Cátedra Ceuta-IATEB" />
        <span class="footer-brand-text">Cátedra Ceuta-IATEB<br>Universidad de Granada · Ciudad Autónoma de Ceuta</span>
      </div>
      <div class="footer-links">
        <a href="https://catedras.ugr.es/iateb/" target="_blank" rel="noopener">Cátedra Ceuta-IATEB</a>
        <a href="https://www.ugr.es" target="_blank" rel="noopener">ugr.es</a>
        <a href="https://www.ceuta.es" target="_blank" rel="noopener">ceuta.es</a>
      </div>
    </div>
  </div>
</footer>


  <script>
    const SUBCATEGORIAS = @json($subcategoriasPorCategoria);
    const OLD_CATEGORIA = @json(old('categoria_id'));
    const OLD_SUBS = @json(array_map('strval', (array) old('subcategorias', [])));

    // Subcategorías dependientes de la categoría, máximo 2
    (function () {
      const select = document.getElementById('categoria_id');
      const cont = document.getElementById('subcategorias-cont');
      const MAX = 2;

      function enforce() {
        const boxes = Array.from(cont.querySelectorAll('input[type=checkbox]'));
        const marcados = boxes.filter(b => b.checked).length;
        boxes.forEach(b => { b.disabled = (!b.checked && marcados >= MAX); });
      }

      function render(catId, marcados) {
        marcados = marcados || [];
        cont.innerHTML = '';
        const subs = SUBCATEGORIAS[catId] || [];
        if (!subs.length) {
          cont.innerHTML = '<p class="form-note" style="margin:0;">Elige primero una categoría.</p>';
          return;
        }
        subs.forEach(function (s) {
          const label = document.createElement('label');
          label.className = 'checkbox-line';
          const check = document.createElement('input');
          check.type = 'checkbox';
          check.name = 'subcategorias[]';
          check.value = s.id;
          if (marcados.indexOf(String(s.id)) !== -1) check.checked = true;
          label.appendChild(check);
          label.appendChild(document.createTextNode(' ' + s.nombre));
          cont.appendChild(label);
        });
        enforce();
      }

      cont.addEventListener('change', enforce);
      select.addEventListener('change', function () { render(select.value, []); });
      if (OLD_CATEGORIA) { render(String(OLD_CATEGORIA), OLD_SUBS); }
    })();

    // Zona: activar/desactivar con "No deseo indicarlo"
    (function () {
      const zona = document.getElementById('zona');
      const omitir = document.getElementById('zona-omitir');
      omitir.addEventListener('change', function () {
        zona.disabled = omitir.checked;
        if (omitir.checked) zona.value = '';
      });
    })();
  </script>
</body>
</html>
