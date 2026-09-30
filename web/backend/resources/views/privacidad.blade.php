{{--
    resources/views/privacidad.blade.php
    Información de privacidad del buzón «Ceuta Me Preocupa».
    Usa la misma hoja de estilos (public/styles.css) y la misma estructura que el index.

    Lo pendiente de confirmar va marcado con <mark class="pendiente">. Busca "pendiente" antes de publicar.
--}}
@php
    // Correo de contacto del proyecto para ejercer derechos. Cámbialo aquí (o muévelo a config/.env).
    $contacto = 'catedraiateb@ugr.es';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="Información sobre el tratamiento de datos personales en el buzón ciudadano Ceuta Me Preocupa.">
<title>Privacidad · Ceuta Me Preocupa</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('paloma2.svg') }}">
<link rel="stylesheet" href="{{ asset('styles.css') }}">
<style>
  /* Estilos propios de esta página, sobre los tokens de styles.css */
  .hero-privacidad .hero-inner{padding-block:48px 52px;}
  .hero-privacidad h1{font-size:clamp(2rem, 4.6vw, 3rem);}

  .resumen{
    background:var(--surface);
    border:1px solid var(--line);
    border-left:4px solid var(--primary);
    border-radius:12px;
    padding:24px 28px;
    margin:0;
  }
  .resumen dl{margin:0; display:grid; grid-template-columns:1fr; gap:4px 24px;}
  @media (min-width:640px){ .resumen dl{grid-template-columns:max-content 1fr;} }
  .resumen dt{font-weight:700; font-size:.9rem; color:var(--ink); padding-top:10px;}
  .resumen dd{margin:0; color:var(--ink-soft); font-size:.95rem; line-height:1.55;}
  @media (min-width:640px){ .resumen dd{padding-top:10px;} }
  .resumen dt:first-of-type, .resumen dd:first-of-type{padding-top:0;}

  .privacidad-layout{display:grid; grid-template-columns:1fr; gap:40px;}
  @media (min-width:900px){
    .privacidad-layout{grid-template-columns:240px 1fr; gap:56px; align-items:start;}
    .indice{position:sticky; top:84px;}
  }
  .indice{
    background:var(--surface);
    border:1px solid var(--line);
    border-radius:12px;
    padding:18px 20px;
  }
  .indice .kicker{margin-bottom:10px;}
  .indice ol{margin:0; padding-left:1.2rem; display:flex; flex-direction:column; gap:6px;}
  .indice a{
    color:var(--ink-soft);
    font-size:.88rem;
    text-decoration:none;
    line-height:1.4;
  }
  .indice a:hover{color:var(--primary); text-decoration:underline; text-underline-offset:3px;}

  .legal{max-width:68ch;}
  .legal article{padding-block:28px; border-top:1px solid var(--line); scroll-margin-top:84px;}
  .legal article:first-child{border-top:none; padding-top:0;}
  .legal h2{font-size:clamp(1.25rem, 2.4vw, 1.5rem); margin-bottom:12px;}
  .legal h2 .num{color:var(--primary); font-variant-numeric:tabular-nums; margin-right:.3em;}
  .legal p, .legal li{color:var(--ink-soft); font-size:1rem; line-height:1.7;}
  .legal p{margin:0 0 12px;}
  .legal p:last-child{margin-bottom:0;}
  .legal ul{margin:0 0 12px; padding-left:1.2rem; display:flex; flex-direction:column; gap:6px;}
  .legal strong{color:var(--ink);}
  .legal a{color:var(--primary); text-decoration:underline; text-underline-offset:3px;}
  .legal .notice{margin-top:4px;}
  .legal .notice p{margin:0;}

  .actualizado{color:#B7CBEE; font-size:.9rem;}

  mark.pendiente{background:#FFE58A; color:#152238; padding:0 .2em; border-radius:3px;}
</style>
</head>
<body>
<header class="nav">
  <div class="wrap">
    <a class="nav-brand" href="{{ url('/') }}" style="text-decoration:none;">
      <img src="{{ asset('logo-nav.webp') }}" alt="Cátedra Ceuta-IATEB" />
      <span>Ceuta Me Preocupa</span>
    </a>
    <a class="btn btn-accent btn-sm" href="{{ url('/') }}#formulario">Volver al formulario</a>
  </div>
</header>

<main>
  <section class="hero hero-privacidad">
    <div class="wrap">
      <div class="hero-inner">
        <span class="eyebrow">Buzón ciudadano · Cátedra Ceuta-IATEB</span>
        <h1>Información de privacidad</h1>
        <p class="lead">Qué datos recoge el buzón «Ceuta Me Preocupa», para qué los usamos, cómo los protegemos y qué derechos tienes sobre ellos.</p>
        <span class="actualizado">Última actualización: 29/09/2026</span>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="section-head">
        <span class="kicker">En resumen</span>
        <h2>Lo esencial, de un vistazo</h2>
      </div>
      <div class="resumen">
        <dl>
          <dt>Responsable</dt>
          <dd>Universidad de Granada, a través de la Cátedra Ceuta-IATEB.</dd>
          <dt>Qué datos</dt>
          <dd>El contenido de tu preocupación. Tu contacto solo si decides dejarlo.</dd>
          <dt>Finalidad</dt>
          <dd>Analizar las preocupaciones ciudadanas, derivarlas al organismo competente y publicar resultados agregados.</dd>
          <dt>Legitimación</dt>
          <dd>Tu consentimiento.</dd>
          <dt>Destinatarios</dt>
          <dd>No se ceden datos identificativos. Solo se comparte información seudonimizada o agregada.</dd>
          <dt>Derechos</dt>
          <dd>Acceso, rectificación, supresión, oposición, limitación y portabilidad. <a href="#derechos" style="color:var(--primary);">Cómo ejercerlos</a>.</dd>
        </dl>
      </div>
    </div>
  </section>

  <section class="band">
    <div class="wrap">
      <div class="privacidad-layout">
        <nav class="indice" aria-label="Índice">
          <span class="kicker">Contenido</span>
          <ol>
            <li><a href="#responsable">Responsable</a></li>
            <li><a href="#dpd">Delegado de Protección de Datos</a></li>
            <li><a href="#datos">Qué datos tratamos</a></li>
            <li><a href="#finalidad">Para qué los usamos</a></li>
            <li><a href="#base">Base jurídica</a></li>
            <li><a href="#seguridad">Seudonimización y seguridad</a></li>
            <li><a href="#destinatarios">Destinatarios</a></li>
            <li><a href="#conservacion">Conservación</a></li>
            <li><a href="#derechos">Tus derechos</a></li>
            <li><a href="#sensibles">Datos sensibles y de terceros</a></li>
            <li><a href="#cookies">Cookies</a></li>
            <li><a href="#limites">Límites del buzón</a></li>
            <li><a href="#cambios">Cambios</a></li>
          </ol>
        </nav>

        <div class="legal">
          <article id="responsable">
            <h2><span class="num">1.</span>Responsable del tratamiento</h2>
            <p>Universidad de Granada (CIF Q1818002F), Avda. del Hospicio s/n, 18071 Granada, a través de la Cátedra UGR – Ciudad Autónoma de Ceuta en Inteligencia Artificial y Tecnologías Emergentes para el Bienestar (Cátedra Ceuta-IATEB), iniciativa conjunta de la Universidad de Granada y la Ciudad Autónoma de Ceuta.</p>
            <p>Contacto del proyecto: <a href="mailto:{{ $contacto }}">{{ $contacto }}</a></p>
          </article>

          <article id="dpd">
            <h2><span class="num">2.</span>Delegado de Protección de Datos</h2>
            <p>Puedes contactar con el Delegado de Protección de Datos de la Universidad de Granada en <a href="mailto:dpd@ugr.es">dpd@ugr.es</a>.</p>
          </article>

          <article id="datos">
            <h2><span class="num">3.</span>Qué datos tratamos</h2>
            <p>El formulario está diseñado para recoger el mínimo de datos. <strong>No pedimos DNI, nombre, dirección, correo, teléfono ni fecha de nacimiento.</strong> Tratamos:</p>
            <ul>
              <li>La categoría y subcategorías que eliges.</li>
              <li>La descripción de tu preocupación o propuesta y, si lo indicas, el resultado que esperas.</li>
              <li>La zona o barrio aproximado, solo si decides indicarlo.</li>
              <li>Un medio de contacto (correo o teléfono), <strong>solo si lo dejas voluntariamente</strong> para recibir respuesta individual. Se guarda cifrado y separado del resto.</li>
              <li>Datos técnicos mínimos para el funcionamiento y la seguridad del servicio, como la fecha y hora del envío.</li>
            </ul>
            <p>Si no dejas contacto, tu envío es anónimo para nosotros, salvo que tú mismo incluyas datos identificativos en el texto.</p>
          </article>

          <article id="finalidad">
            <h2><span class="num">4.</span>Para qué los usamos</h2>
            <ul>
              <li>Recoger y analizar las preocupaciones, incidencias no urgentes y propuestas de mejora sobre la gestión pública de Ceuta.</li>
              <li>Clasificarlas y proponer el organismo previsiblemente competente, trasladándolas de forma seudonimizada cuando proceda.</li>
              <li>Publicar cada mes resultados agregados: recuentos por categoría, temas principales, organismos a los que se ha derivado cada asunto y acciones en curso.</li>
              <li>Elaborar informes y estudios de la Cátedra con información agregada.</li>
              <li>Responderte individualmente, solo si nos has dejado un contacto.</li>
            </ul>
            <p><strong>Nunca publicamos los textos originales</strong> ni información que permita identificarte. No usamos tus datos con fines comerciales ni tomamos decisiones automatizadas que te afecten.</p>
          </article>

          <article id="base">
            <h2><span class="num">5.</span>Base jurídica</h2>
            <p>Tratamos tus datos sobre la base de tu consentimiento (artículo 6.1.a del Reglamento General de Protección de Datos), que otorgas al marcar la casilla de aceptación y enviar el formulario. Puedes retirarlo en cualquier momento, sin que afecte a la licitud del tratamiento anterior.</p>
          </article>

          <article id="seguridad">
            <h2><span class="num">6.</span>Seudonimización y seguridad</h2>
            <ul>
              <li><strong>Identificador aleatorio.</strong> Cada envío recibe un identificador que no está vinculado a tu identidad.</li>
              <li><strong>Seudonimización del texto.</strong> El sistema detecta patrones de datos personales (nombres, correos, teléfonos…) y los sustituye. Una persona revisa una muestra antes de compartir cualquier resultado.</li>
              <li><strong>Separación y cifrado.</strong> El texto original y el contacto se guardan separados del registro de trabajo. El contacto se almacena cifrado.</li>
              <li><strong>Acceso por niveles.</strong> Solo el equipo mínimo autorizado accede al texto original, mediante credenciales individuales. El resto del proceso trabaja con la versión seudonimizada.</li>
            </ul>
          </article>

          <article id="destinatarios">
            <h2><span class="num">7.</span>Destinatarios</h2>
            <p>No cedemos datos personales a terceros, salvo obligación legal. A los organismos públicos competentes solo se traslada el contenido seudonimizado o información agregada.</p>
          </article>

          <article id="conservacion">
            <h2><span class="num">8.</span>Conservación</h2>
            <ul>
              <li><strong>Texto original:</strong> plazo hasta completar su revisión y seudonimización, máximo 3 meses.</li>
              <li><strong>Registro seudonimizado:</strong> mientras dure el proyecto y el plazo necesario para su análisis.</li>
              <li><strong>Contacto:</strong> se suprime una vez atendida tu solicitud y, como máximo, a los 3 meses.</li>
            </ul>
            <p>Los resultados publicados son agregados y pueden conservarse indefinidamente.</p>
          </article>

          <article id="derechos">
            <h2><span class="num">9.</span>Tus derechos</h2>
            <p>Puedes ejercer en cualquier momento tus derechos de acceso, rectificación, supresión, oposición, limitación del tratamiento y portabilidad, así como retirar tu consentimiento, escribiendo a <a href="mailto:{{ $contacto }}">{{ $contacto }}</a>.</p>
            <p>Como el formulario es anónimo, para localizar tu envío necesitaremos que nos indiques datos que permitan identificarlo, como la fecha aproximada y parte del texto. Si no es posible localizarlo, te lo comunicaremos.</p>
            <p>Si consideras que no hemos atendido correctamente tus derechos, puedes reclamar ante la <a href="https://www.aepd.es" target="_blank" rel="noopener">Agencia Española de Protección de Datos</a>.</p>
          </article>

          <article id="sensibles">
            <h2><span class="num">10.</span>Datos sensibles y de terceros</h2>
            <div class="notice">
              <p>Te pedimos que <strong>no incluyas</strong> en tu mensaje datos de salud, ideología, religión, origen étnico, vida sexual u otros datos especialmente protegidos, ni datos que permitan identificar a otras personas. Si aparecen, se eliminarán o seudonimizarán durante la revisión.</p>
            </div>
          </article>

          <article id="cookies">
            <h2><span class="num">11.</span>Cookies</h2>
            <p>Esta web solo usa las cookies técnicas imprescindibles para su funcionamiento: una cookie de sesión y otra de seguridad que protege el formulario frente a envíos fraudulentos. No usamos cookies de analítica, publicidad ni seguimiento.</p>
          </article>

          <article id="limites">
            <h2><span class="num">12.</span>Límites del buzón</h2>
            <ul>
              <li>Una sugerencia no equivale a una reclamación administrativa.</li>
              <li>El envío no abre ningún expediente ni garantiza que la medida se adopte.</li>
              <li>No es un canal de emergencias ni de denuncia. Si tu situación es urgente, dirígete directamente al servicio competente.</li>
            </ul>
          </article>

          <article id="cambios">
            <h2><span class="num">13.</span>Cambios en esta información</h2>
            <p>Podemos actualizar este texto para adaptarlo a cambios normativos o en el funcionamiento del buzón. La fecha de la última actualización figura al principio de la página.</p>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-final">
    <div class="wrap">
      <div>
        <h2>¿Todo claro?</h2>
        <p>Puedes volver al formulario y contarnos tu preocupación.</p>
      </div>
      <a class="btn btn-accent" href="{{ url('/') }}#formulario">Ir al formulario →</a>
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
        <a href="{{ route('privacidad') }}">Privacidad</a>
        <a href="https://catedras.ugr.es/iateb/" target="_blank" rel="noopener">Cátedra Ceuta-IATEB</a>
        <a href="https://www.ugr.es" target="_blank" rel="noopener">ugr.es</a>
        <a href="https://www.ceuta.es" target="_blank" rel="noopener">ceuta.es</a>
      </div>
    </div>
  </div>
</footer>
</body>
</html>
