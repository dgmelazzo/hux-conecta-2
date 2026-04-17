<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Associe-se — ACIC Conecta</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --orange: #E8701A; --orange-hover: #d06316;
            --blue: #1B2B6B; --blue-dark: #0F1B45;
            --bg: #FAFBFC; --white: #FFFFFF;
            --gray-50: #F9FAFB; --gray-100: #F3F4F6; --gray-200: #E5E7EB;
            --gray-300: #D1D5DB; --gray-500: #6B7280; --gray-700: #374151; --gray-900: #111827;
            --green: #10B981; --red: #EF4444;
            --radius: 16px; --radius-sm: 10px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 12px 40px rgba(0,0,0,0.12);
        }
        body { font-family: 'Montserrat', -apple-system, sans-serif; background: var(--bg); color: var(--gray-900); line-height: 1.6; -webkit-font-smoothing: antialiased; }

        /* NAV — sticky clean */
        .acs-nav { background: var(--white); border-bottom: 1px solid var(--gray-200); padding: 0 32px; height: 72px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .acs-nav-logo img { height: 44px; }
        .acs-nav-login { color: var(--white); background: var(--blue); text-decoration: none; font-size: 13px; font-weight: 700; padding: 10px 24px; border-radius: 8px; transition: all 0.2s; letter-spacing: 0.3px; }
        .acs-nav-login:hover { background: var(--blue-dark); transform: translateY(-1px); }

        /* CONTAINER */
        .acs-container { max-width: 900px; margin: 0 auto; padding: 0 20px 80px; }

        /* HERO — gradiente com mais presenca */
        .hero { background: linear-gradient(135deg, var(--blue) 0%, #2d4a9a 60%, #3b5cc6 100%); border-radius: 0 0 24px 24px; padding: 48px 32px 40px; color: #fff; text-align: center; margin: 0 -20px 32px; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -100px; right: -100px; width: 300px; height: 300px; background: rgba(232,112,26,.1); border-radius: 50%; }
        .hero::after { content: ''; position: absolute; bottom: -80px; left: -60px; width: 200px; height: 200px; background: rgba(255,255,255,.04); border-radius: 50%; }
        .hero h1 { font-size: 32px; font-weight: 800; color: #fff; margin-bottom: 8px; position: relative; z-index: 1; }
        .hero p { font-size: 16px; color: rgba(255,255,255,.8); margin-bottom: 32px; position: relative; z-index: 1; }

        /* PROGRESS BAR — sobre o hero */
        .acs-progress-bar { display: flex; align-items: center; justify-content: center; gap: 0; max-width: 500px; margin: 0 auto; position: relative; z-index: 1; }
        .step-wrapper { display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .step-circle { width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,.12); color: rgba(255,255,255,.5); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; transition: all 0.3s; border: 2px solid transparent; }
        .step-circle.active { background: var(--orange); color: #fff; border-color: rgba(255,255,255,.3); box-shadow: 0 0 20px rgba(232,112,26,.4); }
        .step-circle.done { background: var(--green); color: #fff; }
        .step-label { font-size: 11px; font-weight: 600; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: 0.5px; width: 70px; text-align: center; }
        .step-label.active { color: var(--orange); }
        .step-label.done { color: var(--green); }
        .step-connector { flex: 1; height: 3px; background: rgba(255,255,255,.12); min-width: 24px; border-radius: 2px; transition: background 0.3s; }
        .step-connector.done { background: var(--green); }

        /* WIZARD STEPS */
        .wizard-step { display: none; }
        .wizard-step.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* SECTIONS — cards brancos */
        .acs-section { background: var(--white); border-radius: var(--radius); padding: 32px; margin-bottom: 20px; box-shadow: var(--shadow); border: 1px solid var(--gray-100); }
        .acs-section-title { display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 700; color: var(--gray-900); margin-bottom: 24px; }
        .acs-section-title .icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }

        /* PLANOS — grid premium */
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
        .plan-card { background: var(--white); border: 2px solid var(--gray-200); border-radius: var(--radius); padding: 28px 22px; cursor: pointer; transition: all 0.25s; position: relative; overflow: hidden; }
        .plan-card:hover { border-color: var(--orange); box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .plan-card.selected { border-color: var(--orange); background: rgba(232,112,26,.03); box-shadow: 0 0 0 3px rgba(232,112,26,.15); }
        .plan-badge { position: absolute; top: 12px; right: -28px; background: var(--orange); color: #fff; font-size: 9px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; padding: 4px 36px; transform: rotate(45deg); }
        .plan-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px; }
        .plan-name { font-size: 17px; font-weight: 700; color: var(--gray-900); margin-bottom: 8px; }
        .plan-price { font-size: 28px; font-weight: 800; color: var(--blue); margin-bottom: 4px; }
        .plan-price small { font-size: 13px; font-weight: 500; color: var(--gray-500); }
        .plan-desc { font-size: 13px; color: var(--gray-500); line-height: 1.5; margin-bottom: 16px; }
        .plan-select-btn { width: 100%; padding: 12px; border: 2px solid var(--orange); background: transparent; color: var(--orange); border-radius: var(--radius-sm); font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .plan-select-btn:hover { background: var(--orange); color: #fff; }
        .plan-card.selected .plan-select-btn { background: var(--orange); color: #fff; }

        /* FORM FIELDS */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
        .form-row.single { grid-template-columns: 1fr; }
        .form-row.triple { grid-template-columns: 1fr 1fr 1fr; }
        .field, .form-group { margin-bottom: 0; position: relative; }
        .field label, .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--gray-700); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .field label .required, .form-group label .required { color: var(--orange); }
        .field input, .field select, .field textarea,
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 13px 16px; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm);
            font-family: inherit; font-size: 14px; color: var(--gray-900); background: var(--white);
            transition: all 0.2s ease; outline: none; appearance: none;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .form-group select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B7280' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
        .field input:hover, .form-group input:hover,
        .field select:hover, .form-group select:hover { border-color: var(--gray-300); }
        .field input:focus, .field select:focus, .field textarea:focus,
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--orange); box-shadow: 0 0 0 3px rgba(232,112,26,.1); background: var(--white);
        }
        .field input.valid, .form-group input.valid { border-color: var(--green); background: #f0fdf4; }
        .field input.error, .field select.error,
        .form-group input.error, .form-group select.error { border-color: var(--red); background: #fef2f2; }
        .field input:read-only, .form-group input:read-only { background: var(--gray-100); color: var(--gray-500); cursor: not-allowed; }
        .field input::placeholder, .form-group input::placeholder { color: var(--gray-300); font-weight: 400; }
        .error-msg { font-size: 11px; color: var(--red); margin-top: 4px; display: none; font-weight: 500; }
        .error-msg.show { display: block; }

        
        /* Input loading indicator */
        .input-loading { position: relative; }
        .input-loading::after { content: ''; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; border: 2px solid var(--gray-200); border-top-color: var(--orange); border-radius: 50%; animation: spin 0.6s linear infinite; }

        /* Tooltip helper */
        .field-hint { font-size: 11px; color: var(--gray-500); margin-top: 4px; }

        /* Better section spacing */
        .section + .acs-section { margin-top: 0; }

        /* Checkbox LGPD */
        .lgpd-check { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13px; color: var(--gray-700); line-height: 1.6; padding: 16px; background: var(--gray-50); border-radius: var(--radius-sm); border: 1px solid var(--gray-200); margin-bottom: 16px; }
        .lgpd-check input[type="checkbox"] { min-width: 18px; height: 18px; accent-color: var(--orange); margin-top: 2px; cursor: pointer; }
        .lgpd-check a { color: var(--orange); text-decoration: underline; font-weight: 600; }

        /* Better nav actions */
        .step-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--gray-100); }

        /* PAYMENT */
        .payment-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .payment-tab { flex: 1; padding: 14px; border: 2px solid var(--gray-200); border-radius: var(--radius-sm); text-align: center; cursor: pointer; font-weight: 600; font-size: 14px; color: var(--gray-500); transition: all 0.2s; background: var(--white); }
        .payment-tab.active { border-color: var(--orange); color: var(--orange); background: rgba(232,112,26,.04); }
        .payment-tab:hover { border-color: var(--gray-300); }

        /* SUMMARY */
        .summary-box { background: var(--gray-50); border-radius: var(--radius-sm); padding: 20px; margin-bottom: 20px; border: 1px solid var(--gray-200); }
        .summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; color: var(--gray-700); }
        .summary-row.total { border-top: 2px solid var(--gray-200); margin-top: 8px; padding-top: 12px; font-weight: 700; font-size: 16px; color: var(--gray-900); }

        /* BUTTONS */
        .btn-next { background: var(--orange); color: #fff; border: none; padding: 14px 32px; border-radius: var(--radius-sm); font-family: inherit; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-next:hover { background: var(--orange-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(232,112,26,.25); }
        .btn-back { background: none; border: none; color: var(--gray-500); font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px; font-family: inherit; }
        .btn-back:hover { color: var(--gray-700); }
        .step-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; }

        /* SITUACAO */
        .situacao-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .situacao-badge.ativa { background: #dcfce7; color: #166534; }
        .situacao-badge.irregular { background: #fee2e2; color: #991b1b; }

        /* PASSWORD */
        .password-strength { height: 4px; background: var(--gray-200); border-radius: 2px; margin-top: 8px; overflow: hidden; }
        .password-strength-bar { height: 100%; border-radius: 2px; transition: width 0.3s, background 0.3s; width: 0; }
        .password-hint { font-size: 11px; color: var(--gray-500); margin-top: 4px; display: block; }

        /* CONFIRMATION */
        .confirmation { text-align: center; padding: 40px 20px; }
        .confirmation .check-icon { font-size: 56px; margin-bottom: 16px; }
        .confirmation h2 { font-size: 24px; font-weight: 800; color: var(--blue); margin-bottom: 8px; }
        .confirmation p { color: var(--gray-500); font-size: 15px; margin-bottom: 24px; line-height: 1.6; }
        .next-steps { text-align: left; background: var(--gray-50); border-radius: var(--radius-sm); padding: 24px; margin: 24px 0; border: 1px solid var(--gray-200); }
        .next-steps h3 { font-size: 15px; font-weight: 700; margin-bottom: 12px; color: var(--gray-900); }
        .next-steps ol { padding-left: 20px; }
        .next-steps li { padding: 6px 0; font-size: 14px; color: var(--gray-700); }
        .btn-portal { display: inline-block; background: var(--orange); color: #fff; text-decoration: none; padding: 14px 32px; border-radius: var(--radius-sm); font-weight: 700; font-size: 15px; transition: all 0.2s; }
        .btn-portal:hover { background: var(--orange-hover); transform: translateY(-1px); }
        .qr-placeholder { margin: 20px auto; display: flex; justify-content: center; }
        .qr-placeholder img { max-width: 220px; border-radius: 12px; border: 1px solid var(--gray-200); padding: 12px; background: #fff; }

        /* LOADING */
        .plans-loading { text-align: center; padding: 40px; color: var(--gray-500); }
        .spinner { width: 28px; height: 28px; border: 3px solid var(--gray-200); border-top-color: var(--orange); border-radius: 50%; animation: spin 0.6s linear infinite; margin: 0 auto 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* FOOTER */
        .footer { text-align: center; padding: 32px 20px; color: var(--gray-500); font-size: 13px; }
        .footer a { color: var(--blue); text-decoration: none; font-weight: 500; }
        .footer-selos { display: flex; justify-content: center; gap: 20px; margin-top: 12px; font-size: 12px; color: var(--gray-300); }

        /* RESPONSIVE */
        @media (max-width: 640px) {
            .acs-container { padding: 0 16px 60px; }
            .hero { padding: 32px 20px; margin: 0 -16px 24px; }
            .hero h1 { font-size: 24px; }
            .acs-section { padding: 24px 18px; }
            .form-row, .form-row.triple { grid-template-columns: 1fr; }
            .plans-grid { grid-template-columns: 1fr; }
            .step-connector { min-width: 16px; }
            .step-label { width: 56px; font-size: 9px; }
            .step-circle { width: 36px; height: 36px; font-size: 13px; }
            .step-nav { flex-direction: column-reverse; gap: 12px; }
            .btn-next { width: 100%; }
            .btn-back { justify-content: center; }
            .acs-nav { padding: 0 16px; height: 60px; }
            .acs-nav-logo img { height: 32px; }
        }
    
    /* Bootstrap overrides for associe-se */
    .form-group label { font-size: 12px; font-weight: 700; color: var(--gray-700); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px; display: block; }
    .form-group label .required { color: var(--orange); font-weight: 800; }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 12px 16px; border: 1.5px solid var(--gray-200); border-radius: 10px;
      font-family: inherit; font-size: 14px; color: var(--gray-900); background: #fff;
      transition: all 0.2s; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,.04);
    }
    .form-group input:hover, .form-group select:hover { border-color: var(--gray-300); }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
      border-color: var(--orange); box-shadow: 0 0 0 3px rgba(232,112,26,.12); background: #fff;
    }
    .form-group input:read-only { background: var(--gray-100); color: var(--gray-500); cursor: not-allowed; }
    .form-group input::placeholder { color: var(--gray-300); }
    .form-group select {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B7280' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px;
    }

    /* Botoes modernos */
    .btn-next { background: var(--orange); color: #fff; border: none; padding: 14px 32px; border-radius: 10px;
      font-family: inherit; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(232,112,26,.25); }
    .btn-next:hover { background: var(--orange-hover); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(232,112,26,.3); }
    .btn-back { background: none; border: none; color: var(--gray-500); font-size: 14px; font-weight: 600;
      cursor: pointer; display: flex; align-items: center; gap: 6px; font-family: inherit; transition: all .2s; }
    .btn-back:hover { color: var(--gray-700); }

    /* Plan cards */
    .plan-card { border-radius: 14px; transition: all 0.25s; }
    .plan-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
    .plan-card.selected { border-color: var(--orange); box-shadow: 0 0 0 3px rgba(232,112,26,.15), 0 8px 24px rgba(232,112,26,.1); }
    .plan-select-btn { border-radius: 10px; font-weight: 700; transition: all 0.2s; }

    /* Section cards */
    .acs-section { border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }

    /* Step circles */
    .step-circle.active { box-shadow: 0 0 20px rgba(232,112,26,.4), 0 0 0 3px rgba(232,112,26,.15); }

    /* Progress connector */
    .step-connector.done { background: var(--green); }

    /* LGPD */
    .lgpd-check { border-radius: 10px; }

    /* Step nav */
    .step-nav { border-top: 1px solid var(--gray-100); padding-top: 20px; }

    /* Confirmation */
    .confirmation .check-icon { font-size: 56px; }
    .btn-portal { border-radius: 10px; font-weight: 700; box-shadow: 0 2px 8px rgba(232,112,26,.25); }
    .btn-portal:hover { box-shadow: 0 4px 16px rgba(232,112,26,.3); transform: translateY(-1px); }

    /* Hero gradient */
    .hero { border-radius: 16px; overflow: hidden; }

    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 3px; }

    
    /* Reset Bootstrap conflicts */
    body { margin: 0 !important; padding: 0 !important; }
    .acs-container { max-width: 900px; margin: 0 auto; padding: 0 20px 80px; }
    .acs-nav { background: var(--white); border-bottom: 1px solid var(--gray-200); padding: 0 32px; height: 72px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .acs-section { background: var(--white); border-radius: 14px; padding: 32px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.04); border: 1px solid var(--gray-100); }

    </style>
<body>

<!-- NAV -->
<nav class="acs-nav">
    <a href="/" class="acs-nav-logo"><img src="/conecta/uploads/logo-light-320.png" alt="ACIC Conecta" style="height:40px"></a>
    <div style="display:flex;align-items:center;gap:16px">
      <span style="font-size:12px;color:var(--gray-500)" class="d-none d-md-inline"><i class="bi bi-telephone"></i> (61) 3371-2165</span>
      <a href="/" class="acs-nav-login"><i class="bi bi-box-arrow-in-right"></i> Já sou associado</a>
    </div>
</nav>

<!-- MAIN -->
<div class="acs-container">

    <!-- HERO -->
    <div class="hero" style="background:linear-gradient(135deg,#1B2B6B 0%,#2d4a9a 100%);border-radius:16px;padding:32px 28px;color:#fff;margin-bottom:24px">
        <h1 style="color:#fff">Associe-se à ACIC-DF</h1>
        <p style="color:rgba(255,255,255,.8)">Faça parte da maior rede de comércio do Distrito Federal</p>

        <div class="acs-progress-bar" id="progress-bar">
            <div class="step-wrapper">
                <div class="step-circle active" id="step-circle-1" data-step="1">1</div>
                <div class="step-label active" id="step-label-1">Plano</div>
            </div>
            <div class="step-connector" id="conn-1"></div>
            <div class="step-wrapper">
                <div class="step-circle" id="step-circle-2" data-step="2">2</div>
                <div class="step-label" id="step-label-2">Empresa</div>
            </div>
            <div class="step-connector" id="conn-2"></div>
            <div class="step-wrapper">
                <div class="step-circle" id="step-circle-3" data-step="3">3</div>
                <div class="step-label" id="step-label-3">Responsável</div>
            </div>
            <div class="step-connector" id="conn-3"></div>
            <div class="step-wrapper">
                <div class="step-circle" id="step-circle-4" data-step="4">4</div>
                <div class="step-label" id="step-label-4">Pagamento</div>
            </div>
        </div>
    </div>

    <!-- ============ STEP 1: PLANO ============ -->
    <div class="wizard-step active" id="wizard-step-1">
        <div class="acs-section">
            <div class="acs-section-title">
                <div class="icon" style="background:rgba(232,112,26,.1);color:var(--orange);"><i class="bi bi-list-check"></i></div>
                Escolha seu plano
            </div>
            <div class="plans-grid" id="plans-grid">
                <div class="plans-loading">
                    <div class="spinner"></div>
                    Carregando planos...
                </div>
            </div>
        </div>
    </div>

    <!-- ============ STEP 2: DADOS DA EMPRESA ============ -->
    <div class="wizard-step" id="wizard-step-2">
        <div class="acs-section">
            <div class="acs-section-title">
                <div class="icon" style="background:rgba(27,43,107,.08);color:var(--blue);"><i class="bi bi-building"></i></div>
                Dados da empresa
            </div>

            <div class="form-row single">
                <div class="form-group">
                    <label>CNPJ <span class="required">*</span></label>
                    <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" maxlength="18" required>
                    <span class="error-msg" id="cnpj-error">CNPJ inválido</span>
                </div>
            </div>
            <div id="cnpj-data" style="display:none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Razão social</label>
                        <input type="text" id="razao_social" name="razao_social" readonly>
                    </div>
                    <div class="form-group">
                        <label>Nome fantasia</label>
                        <input type="text" id="fantasia" name="fantasia" placeholder="Nome fantasia">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>CNAE</label>
                        <input type="text" id="cnae" name="cnae" readonly>
                    </div>
                    <div class="form-group">
                        <label>Situação cadastral</label>
                        <div id="situacao-container"></div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Telefone da empresa <span class="required">*</span></label>
                    <input type="text" id="telefone_empresa" name="telefone_empresa" placeholder="(61) 3371-2165" maxlength="15">
                    <span class="error-msg" id="telefone_empresa-error">Telefone inválido</span>
                </div>
                <div class="form-group">
                    <label>E-mail da empresa <span class="required">*</span></label>
                    <input type="email" id="email_empresa" name="email_empresa" placeholder="contato@empresa.com.br">
                    <span class="error-msg" id="email_empresa-error">E-mail inválido</span>
                </div>
            </div>

            <div class="form-row single">
                <div class="form-group">
                    <label>Segmento / Porte <span class="required">*</span></label>
                    <select id="segmento" name="segmento" required>
                        <option value="">Selecione o segmento</option>
                        <option value="MEI">MEI — Microempreendedor Individual</option>
                        <option value="ME">ME — Microempresa</option>
                        <option value="EPP">EPP — Empresa de Pequeno Porte</option>
                        <option value="Outro">Outro</option>
                    </select>
                    <span class="error-msg" id="segmento-error">Selecione o segmento</span>
                </div>
            </div>
        </div>

        <div class="acs-section">
            <div class="acs-section-title">
                <div class="icon" style="background:rgba(27,43,107,.08);color:var(--blue);"><i class="bi bi-geo-alt"></i></div>
                Endereço
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>CEP <span class="required">*</span></label>
                    <input type="text" id="cep" name="cep" placeholder="00000-000" maxlength="9" required>
                    <span class="error-msg" id="cep-error">CEP inválido</span>
                </div>
                <div class="form-group">
                    <label>Logradouro</label>
                    <input type="text" id="logradouro" name="logradouro" placeholder="Rua, Av...">
                </div>
            </div>
            <div class="form-row triple">
                <div class="form-group">
                    <label>Número <span class="required">*</span></label>
                    <input type="text" id="numero" name="numero" placeholder="Nº" required>
                    <span class="error-msg" id="numero-error">Informe o número</span>
                </div>
                <div class="form-group">
                    <label>Complemento</label>
                    <input type="text" id="complemento" name="complemento" placeholder="Sala, Andar...">
                </div>
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" id="bairro" name="bairro" placeholder="Bairro">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" id="cidade" name="cidade" readonly>
                </div>
                <div class="form-group">
                    <label>UF</label>
                    <input type="text" id="uf" name="uf" readonly maxlength="2">
                </div>
            </div>

            <div class="step-nav">
                <button type="button" class="btn-back" onclick="goToStep(1)"><i class="bi bi-arrow-left"></i> <span>Voltar</span></button>
                <button type="button" class="btn-next" onclick="validateAndGoStep3()"><span>Continuar</span> <i class="bi bi-arrow-right"></i></button>
            </div>
        </div>
    </div>

    <!-- ============ STEP 3: RESPONSÁVEL LEGAL ============ -->
    <div class="wizard-step" id="wizard-step-3">
        <div class="acs-section">
            <div class="acs-section-title">
                <div class="icon" style="background:rgba(27,43,107,.08);color:var(--blue);"><i class="bi bi-person-check"></i></div>
                Responsável Legal
            </div>
            <p style="font-size:14px;color:var(--gray-500);margin:-16px 0 24px;">
                O responsável legal será o usuário principal para acessar o portal do associado.
            </p>

            <div class="form-row">
                <div class="form-group">
                    <label>Nome completo <span class="required">*</span></label>
                    <input type="text" id="nome" name="nome" placeholder="Nome do responsável legal" required>
                    <span class="error-msg" id="nome-error">Informe o nome completo</span>
                </div>
                <div class="form-group">
                    <label>CPF <span class="required">*</span></label>
                    <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required>
                    <span class="error-msg" id="cpf-error">CPF inválido</span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>E-mail <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="responsavel@email.com" required>
                    <span class="error-msg" id="email-error">E-mail inválido</span>
                </div>
                <div class="form-group">
                    <label>WhatsApp com DDD <span class="required">*</span></label>
                    <input type="text" id="whatsapp" name="whatsapp" placeholder="(61) 99999-9999" maxlength="15" required>
                    <span class="error-msg" id="whatsapp-error">WhatsApp inválido</span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Senha <span class="required">*</span></label>
                    <input type="password" id="senha" name="senha" placeholder="Mínimo 8 caracteres" minlength="8" required>
                    <div class="password-strength"><div class="password-strength-bar" id="senha-strength"></div></div>
                    <span class="password-hint" id="senha-hint">Use letras, números e caracteres especiais</span>
                    <span class="error-msg" id="senha-error">A senha deve ter no mínimo 8 caracteres</span>
                </div>
                <div class="form-group">
                    <label>Confirmar senha <span class="required">*</span></label>
                    <input type="password" id="senha_confirmar" name="senha_confirmar" placeholder="Repita a senha" required>
                    <span class="error-msg" id="senha_confirmar-error">As senhas não conferem</span>
                </div>
            </div>

            <div style="margin-bottom:16px">
                    <label class="lgpd-check">
                        <input type="checkbox" id="aceite-lgpd" required>
                        <span>Li e concordo com os <a href="#">Termos de Uso</a> e a <a href="#">Política de Privacidade (LGPD)</a>. Autorizo o tratamento dos meus dados pessoais para fins de associação.</span>
                    </label>
                </div>
                <div class="step-nav">
                    <button type="button" class="btn-back" onclick="goToStep(2)"><i class="bi bi-arrow-left"></i> <span>Voltar</span></button>
                    <button type="button" class="btn-next" onclick="validateAndGoStep4()"><span>Continuar</span> <i class="bi bi-arrow-right"></i></button>
                </div>
        </div>
    </div>

    <!-- ============ STEP 4: PAGAMENTO ============ -->
    <div class="wizard-step" id="wizard-step-4">
        <div class="acs-section">
            <div class="acs-section-title">
                <div class="icon" style="background:rgba(232,112,26,.1);color:var(--orange);"><i class="bi bi-credit-card"></i></div>
                Pagamento
            </div>

            <div class="summary-box" id="summary-box">
                <div class="summary-row">
                    <span>Plano</span>
                    <span id="sum-plano">—</span>
                </div>
                <div class="summary-row">
                    <span>Adesão</span>
                    <span id="sum-adesao">—</span>
                </div>
                <div class="summary-row">
                    <span>Recorrência</span>
                    <span id="sum-recorrencia">—</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span id="sum-total">—</span>
                </div>
            </div>

            <div class="payment-tabs">
                <div class="payment-tab active" data-method="pix" onclick="selectPayment('pix')">PIX</div>
                <div class="payment-tab" data-method="boleto" onclick="selectPayment('boleto')">Boleto</div>
                <div class="payment-tab" data-method="cartao" onclick="selectPayment('cartao')">Cartão</div>
            </div>

            <div class="step-nav">
                <button type="button" class="btn-back" onclick="goToStep(3)"><i class="bi bi-arrow-left"></i> <span>Voltar</span></button>
                <button type="button" class="btn-next" id="btn-submit" onclick="submitForm()">Finalizar inscrição &#8594;</button>
            </div>
        </div>
    </div>

    <!-- ============ STEP 5: CONFIRMAÇÃO ============ -->
    <div class="wizard-step" id="wizard-step-5">
        <div class="acs-section">
            <div class="confirmation" id="confirmation-content">
                <div class="check-icon">✅</div>
                <h2>Inscrição realizada!</h2>
                <p>Sua inscrição foi recebida com sucesso. Confira os próximos passos abaixo.</p>
            </div>
        </div>
    </div>

</div>

<!-- FOOTER -->
<footer class="footer">
    <p>Dúvidas? <a href="tel:6133712165">(61) 3371-2165</a> | <a href="mailto:secretaria@acicdf.org.br">secretaria@acicdf.org.br</a></p>
    <div class="footer-selos">
        <span>🔒 LGPD</span>
        <span>💰 Asaas</span>
        <span>🏛 Receita Federal</span>
    </div>
</footer>

<script>
(function() {
    var _isHml = window.location.hostname.indexOf('hml.') !== -1;
    var CRM_API = _isHml ? 'https://hml.crm.acicdf.org.br/api' : 'https://api-crm.acicdf.org.br';
    var PORTAL_URL = _isHml ? 'https://hml.conecta.acicdf.org.br' : 'https://conecta.acicdf.org.br';

    'use strict';

    var TOTAL_STEPS = 4;
    var currentStep = 1;
    var selectedPlan = null;
    var paymentMethod = 'pix';
    var planos = [];
    var cnpjSituacao = '';

    // === MASKS ===
    function maskCPF(v) {
        return v.replace(/\D/g,'').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2').slice(0,14);
    }

    function maskCNPJ(v) {
        return v.replace(/\D/g,'').replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2').slice(0,18);
    }

    function maskPhone(v) {
        v = v.replace(/\D/g,'');
        if (v.length > 10) return v.replace(/^(\d{2})(\d{5})(\d{4}).*/,'($1) $2-$3');
        if (v.length > 6) return v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/,'($1) $2-$3');
        if (v.length > 2) return v.replace(/^(\d{2})(\d{0,5}).*/,'($1) $2');
        return v;
    }

    function maskCEP(v) {
        return v.replace(/\D/g,'').replace(/^(\d{5})(\d)/,'$1-$2').slice(0,9);
    }

    // === MASK BINDINGS ===
    document.getElementById('cpf').addEventListener('input', function() {
        this.value = maskCPF(this.value);
        if (this.value.replace(/\D/g,'').length === 11) {
            this.classList.toggle('valid', validaCPF(this.value));
            this.classList.toggle('error', !validaCPF(this.value));
        } else {
            this.classList.remove('valid', 'error');
        }
    });
    document.getElementById('cnpj').addEventListener('input', function() { this.value = maskCNPJ(this.value); });
    document.getElementById('whatsapp').addEventListener('input', function() {
        this.value = maskPhone(this.value);
        var digits = this.value.replace(/\D/g,'').length;
        if (digits >= 10) {
            this.classList.add('valid');
            this.classList.remove('error');
        } else {
            this.classList.remove('valid');
        }
    });
    document.getElementById('telefone_empresa').addEventListener('input', function() {
        this.value = maskPhone(this.value);
    });
    document.getElementById('cep').addEventListener('input', function() { this.value = maskCEP(this.value); });
    document.getElementById('email').addEventListener('input', function() {
        if (this.value && validaEmail(this.value)) {
            this.classList.add('valid');
            this.classList.remove('error');
        } else {
            this.classList.remove('valid');
        }
    });
    document.getElementById('email_empresa').addEventListener('input', function() {
        if (this.value && validaEmail(this.value)) {
            this.classList.add('valid');
            this.classList.remove('error');
        } else {
            this.classList.remove('valid');
        }
    });

    // === PASSWORD STRENGTH ===
    document.getElementById('senha').addEventListener('input', function() {
        var val = this.value;
        var bar = document.getElementById('senha-strength');
        var hint = document.getElementById('senha-hint');
        var score = 0;
        if (val.length >= 8) score++;
        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/\d/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        var pct = Math.min(score * 20, 100);
        var color = pct <= 20 ? 'var(--red)' : pct <= 40 ? '#f59e0b' : pct <= 60 ? 'var(--orange)' : 'var(--green)';
        bar.style.width = pct + '%';
        bar.style.background = color;

        var labels = ['Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte'];
        hint.textContent = val.length > 0 ? labels[Math.min(score, 4)] : 'Use letras, números e caracteres especiais';
    });

    // === VALIDATE CPF ===
    function validaCPF(cpf) {
        cpf = cpf.replace(/\D/g,'');
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        for (var t = 9; t < 11; t++) {
            var d = 0;
            for (var c = 0; c < t; c++) d += parseInt(cpf[c]) * ((t+1) - c);
            d = ((10 * d) % 11) % 10;
            if (parseInt(cpf[t]) !== d) return false;
        }
        return true;
    }

    function validaEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }
    function validaWhatsApp(w) { return w.replace(/\D/g,'').length >= 10; }
    function validaPhone(p) { return p.replace(/\D/g,'').length >= 10; }

    // === WIZARD NAVIGATION ===
    window.goToStep = function(step) {
        if (step < 1 || step > TOTAL_STEPS + 1) return;
        if (step > currentStep + 1) return;

        document.querySelectorAll('.wizard-step').forEach(function(el) { el.classList.remove('active'); });
        document.getElementById('wizard-step-' + step).classList.add('active');
        currentStep = step;
        updateProgress(step);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    function updateProgress(step) {
        for (var i = 1; i <= TOTAL_STEPS; i++) {
            var circle = document.getElementById('step-circle-' + i);
            var label = document.getElementById('step-label-' + i);
            circle.classList.remove('active', 'done');
            label.classList.remove('active', 'done');

            if (i < step) {
                circle.classList.add('done');
                circle.innerHTML = '✓';
                label.classList.add('done');
            } else if (i === step) {
                circle.classList.add('active');
                circle.innerHTML = i;
                label.classList.add('active');
            } else {
                circle.innerHTML = i;
            }
        }
        for (var j = 1; j <= TOTAL_STEPS - 1; j++) {
            var conn = document.getElementById('conn-' + j);
            conn.classList.toggle('done', j < step);
        }
    }

    document.getElementById('progress-bar').addEventListener('click', function(e) {
        var circle = e.target.closest('.step-circle');
        if (circle && circle.classList.contains('done')) {
            var stepNum = parseInt(circle.dataset.step);
            if (stepNum && stepNum < currentStep) {
                goToStep(stepNum);
            }
        }
    });

    // === LOAD PLANS ===
    async function loadPlans() {
        try {
            var res = await fetch(CRM_API + '/planos');
            planos = (await res.json()).data.data;
            renderPlans();
        } catch(e) {
            document.getElementById('plans-grid').innerHTML =
                '<div class="plans-loading" style="color:var(--red);">Erro ao carregar planos. Recarregue a página.</div>';
        }
    }

    function getPlanIcon(nome) {
        if (/mei/i.test(nome)) return { icon: '🧑‍💼', bg: 'rgba(232,112,26,0.1)' };
        if (/empresa/i.test(nome)) return { icon: '🏢', bg: 'rgba(27,43,107,0.1)' };
        if (/me\b/i.test(nome)) return { icon: '📊', bg: 'rgba(22,163,74,0.1)' };
        return { icon: '⭐', bg: 'rgba(232,112,26,0.1)' };
    }

    function renderPlans() {
        var grid = document.getElementById('plans-grid');
        grid.innerHTML = '';
        planos.forEach(function(p) {
            var card = document.createElement('div');
            card.className = 'plan-card';
            card.dataset.id = p.id;

            var isMeiTop = /mei.?top/i.test(p.nome);
            var iconData = getPlanIcon(p.nome);

            card.innerHTML =
                (isMeiTop ? '<div class="plan-badge">Mais escolhido</div>' : '') +
                '<div class="plan-icon" style="background:' + iconData.bg + ';">' + iconData.icon + '</div>' +
                '<div class="plan-name">' + escapeHTML(p.nome) + '</div>' +
                '<div class="plan-price">R$ ' + formatMoney(p.valor_mensal || p.valor) +
                '<small>/mês</small></div>' +
                (p.descricao ? '<div class="plan-desc">' + escapeHTML(p.descricao) + '</div>' : '') +
                (function(){var h='';if(p.itens&&p.itens.length){h='<ul style="list-style:none;padding:0;margin:12px 0 0;text-align:left">';p.itens.forEach(function(it){h+='<li style="font-size:12px;color:#666;padding:3px 0;display:flex;align-items:center;gap:6px"><span style="color:#22c55e;font-weight:bold">✓</span> '+(it.conecta_produto_nome||it.nome)+(it.tipo_cobranca==="incluso"?' <span style="background:#dcfce7;color:#166534;font-size:10px;padding:1px 6px;border-radius:8px;font-weight:600">incluso</span>':"")+"</li>";});h+="</ul>";}return h;})() +
                '<button type="button" class="plan-select-btn">Escolher este plano</button>';

            card.addEventListener('click', function() { selectPlan(p, card); });
            grid.appendChild(card);
        });
    }

    function selectPlan(plan, card) {
        document.querySelectorAll('.plan-card').forEach(function(c) {
            c.classList.remove('selected');
            var btn = c.querySelector('.plan-select-btn');
            if (btn) btn.textContent = 'Escolher este plano';
        });
        card.classList.add('selected');
        var btn = card.querySelector('.plan-select-btn');
        if (btn) btn.textContent = '✓ Selecionado';
        selectedPlan = plan;
        updateSummary();

        setTimeout(function() { goToStep(2); }, 400);
    }

    function updateSummary() {
        if (!selectedPlan) return;
        document.getElementById('sum-plano').textContent = selectedPlan.nome;
        var adesao = selectedPlan.valor_adesao || selectedPlan.adesao || 0;
        var mensal = selectedPlan.valor_mensal || selectedPlan.valor || 0;
        document.getElementById('sum-adesao').textContent = 'R$ ' + formatMoney(adesao);
        document.getElementById('sum-recorrencia').textContent = 'R$ ' + formatMoney(mensal) + '/mês';
        var total = parseFloat(adesao) + parseFloat(mensal);
        document.getElementById('sum-total').textContent = 'R$ ' + formatMoney(total);
    }

    // === STEP 2 → 3 VALIDATION (Empresa → Responsável) ===
    window.validateAndGoStep3 = function() {
        clearErrors();
        var valid = true;

        var cnpjVal = document.getElementById('cnpj').value.replace(/\D/g,'');
        if (cnpjVal.length !== 14) { showError('cnpj'); valid = false; }

        if (cnpjSituacao && cnpjSituacao !== 'ATIVA') {
            document.getElementById('cnpj-error').textContent = 'Empresa com situação irregular. Não é possível prosseguir.';
            showError('cnpj');
            valid = false;
        }

        if (!validaPhone(document.getElementById('telefone_empresa').value)) { showError('telefone_empresa'); valid = false; }
        if (!validaEmail(document.getElementById('email_empresa').value)) { showError('email_empresa'); valid = false; }
        if (!document.getElementById('segmento').value) { showError('segmento'); valid = false; }

        if (document.getElementById('cep').value.replace(/\D/g,'').length !== 8) { showError('cep'); valid = false; }
        if (!document.getElementById('numero').value.trim()) { showError('numero'); valid = false; }

        if (!valid) {
            var firstErr = document.querySelector('#wizard-step-2 .error');
            if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        goToStep(3);
    };

    // === STEP 3 → 4 VALIDATION (Responsável → Pagamento) ===
    window.validateAndGoStep4 = function() {
        if (!document.getElementById('aceite-lgpd').checked) {
            alert('Voce precisa aceitar os Termos de Uso e a Politica de Privacidade para continuar.');
            return;
        }
        clearErrors();
        var valid = true;

        if (!document.getElementById('nome').value.trim()) { showError('nome'); valid = false; }
        if (!validaCPF(document.getElementById('cpf').value)) { showError('cpf'); valid = false; }
        if (!validaEmail(document.getElementById('email').value)) { showError('email'); valid = false; }
        if (!validaWhatsApp(document.getElementById('whatsapp').value)) { showError('whatsapp'); valid = false; }

        var senha = document.getElementById('senha').value;
        var senhaConfirmar = document.getElementById('senha_confirmar').value;
        if (senha.length < 8) { showError('senha'); valid = false; }
        if (senha !== senhaConfirmar) { showError('senha_confirmar'); valid = false; }

        if (!valid) {
            var firstErr = document.querySelector('#wizard-step-3 .error');
            if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        updateSummary();
        goToStep(4);
    };

    // === CNPJ LOOKUP ===
    document.getElementById('cnpj').addEventListener('blur', async function() {
        var cnpj = this.value.replace(/\D/g,'');
        if (cnpj.length !== 14) return;

        try {
            var res = await fetch('/api.php?action=cnpj&doc=' + cnpj);
            if (!res.ok) throw new Error('CNPJ nao encontrado');
            var data = await res.json();

            document.getElementById('cnpj-data').style.display = 'block';
            document.getElementById('razao_social').value = data.razao_social || '';
            document.getElementById('fantasia').value = data.nome_fantasia || '';

            var cnaeText = '';
            if (data.cnae_fiscal) cnaeText = data.cnae_fiscal + ' - ' + (data.cnae_fiscal_descricao || '');
            document.getElementById('cnae').value = cnaeText;

            var situacao = (data.descricao_situacao_cadastral || '').toUpperCase();
            cnpjSituacao = situacao;
            var container = document.getElementById('situacao-container');
            if (cnpjSituacao === 'ATIVA') {
                container.innerHTML = '<div class="situacao-badge ativa">● ATIVA</div>';
            } else {
                container.innerHTML = '<div class="situacao-badge irregular">● ' + escapeHTML(cnpjSituacao || 'IRREGULAR') + '</div>';
            }

            var porte = (data.porte || '').toUpperCase();
            var sel = document.getElementById('segmento');
            if (/MEI/.test(porte) || data.opcao_pelo_mei) sel.value = 'MEI';
            else if (/MICRO/.test(porte)) sel.value = 'ME';
            else if (/PEQUENO/.test(porte)) sel.value = 'EPP';

            if (data.cep) {
                document.getElementById('cep').value = String(data.cep).replace(/(\d{5})(\d{3})/, '$1-$2');
                document.getElementById('logradouro').value = ((data.descricao_tipo_de_logradouro || '') + ' ' + (data.logradouro || '')).trim();
                document.getElementById('numero').value = data.numero || '';
                document.getElementById('complemento').value = data.complemento || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.municipio || '';
                document.getElementById('uf').value = data.uf || '';
            }
        } catch(e) {
            document.getElementById('cnpj-error').textContent = 'Erro ao consultar CNPJ';
            document.getElementById('cnpj-error').classList.add('show');
        }
    });

    // === CEP LOOKUP ===
    document.getElementById('cep').addEventListener('blur', async function() {
        var cep = this.value.replace(/\D/g,'');
        if (cep.length !== 8) return;

        try {
            var res = await fetch('/api.php?action=cep&doc=' + cep + '');
            var data = await res.json();
            if (data.erro) throw new Error('CEP not found');

            document.getElementById('logradouro').value = data.logradouro || '';
            document.getElementById('bairro').value = data.bairro || '';
            document.getElementById('cidade').value = data.localidade || '';
            document.getElementById('uf').value = data.uf || '';
        } catch(e) {
            document.getElementById('cep-error').textContent = 'CEP não encontrado';
            document.getElementById('cep-error').classList.add('show');
        }
    });

    // === PAYMENT TABS ===
    window.selectPayment = function(method) {
        paymentMethod = method;
        document.querySelectorAll('.payment-tab').forEach(function(t) {
            t.classList.toggle('active', t.dataset.method === method);
        });
    };

    // === FORM SUBMIT ===
    window.submitForm = async function() {
        var btn = document.getElementById('btn-submit');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:20px;height:20px;border-width:2px;margin:0;"></div> Processando...';

        var payload = {
            plano_id: selectedPlan.id,
            cpf_cnpj: document.getElementById('cnpj').value.replace(/\D/g,''),
            nome_fantasia: document.getElementById('fantasia').value,
            razao_social: document.getElementById('razao_social').value,
            cnae: document.getElementById('cnae').value,
            telefone_empresa: document.getElementById('telefone_empresa').value.replace(/\D/g,''),
            email_empresa: document.getElementById('email_empresa').value.trim(),
            segmento: document.getElementById('segmento').value,
            cep: document.getElementById('cep').value.replace(/\D/g,''),
            logradouro: document.getElementById('logradouro').value,
            numero: document.getElementById('numero').value,
            complemento: document.getElementById('complemento').value,
            bairro: document.getElementById('bairro').value,
            cidade: document.getElementById('cidade').value,
            uf: document.getElementById('uf').value,
            nome: document.getElementById('nome').value.trim(),
            cpf: document.getElementById('cpf').value.replace(/\D/g,''),
            email: document.getElementById('email').value.trim(),
            telefone: document.getElementById('whatsapp').value.replace(/\D/g,''),
            whatsapp: document.getElementById('whatsapp').value.replace(/\D/g,''),
            senha: document.getElementById('senha').value,
            metodo_pagamento: paymentMethod
        };

        try {
            var res = await fetch(CRM_API + '/inscricoes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            var result = await res.json();
            var data = result.data || result;
            clearDraft();
            showConfirmation(data);
            goToStep(5);
        } catch(err) {
            alert('Erro ao processar inscrição. Tente novamente.');
            btn.disabled = false;
            btn.innerHTML = 'Finalizar inscrição &#8594;';
        }
    };

    function showConfirmation(result) {
        var content = document.getElementById('confirmation-content');
        var paymentHtml = '';

        if (paymentMethod === 'pix') {
            paymentHtml =
                '<div class="qr-placeholder">' +
                (result.qr_code_url
                    ? '<img src="' + escapeAttr(result.qr_code_url) + '" alt="QR Code PIX">'
                    : (result.qr_code_base64
                        ? '<img src="data:image/png;base64,' + result.qr_code_base64 + '" alt="QR Code PIX">'
                        : 'QR Code será exibido aqui')) +
                '</div>' +
                (result.pix_copia_cola
                    ? '<div class="pix-code" style="background:var(--surface2,#f5f5f5);border:1px solid var(--border,#ddd);border-radius:8px;padding:12px;font-size:11px;word-break:break-all;margin-top:12px;position:relative">' +
                      '<div style="font-size:12px;font-weight:600;margin-bottom:6px;color:var(--text2,#555)">PIX Copia e Cola:</div>' +
                      escapeHTML(result.pix_copia_cola) +
                      '<button onclick="navigator.clipboard.writeText(this.dataset.pix);this.textContent=\'Copiado!\';setTimeout(()=>this.textContent=\'Copiar\',2000)" data-pix="' + escapeAttr(result.pix_copia_cola) + '" style="margin-top:8px;padding:6px 16px;border-radius:6px;border:1px solid var(--orange,#E8701A);background:var(--orange,#E8701A);color:#fff;font-size:12px;font-weight:600;cursor:pointer">Copiar</button>' +
                      '</div>'
                    : '');
        } else if (paymentMethod === 'boleto' && result.boleto_url) {
            paymentHtml = '<a class="boleto-link" href="' + escapeAttr(result.boleto_url) + '" target="_blank">Abrir boleto</a>';
        }

        content.innerHTML =
            '<div class="check-icon">✅</div>' +
            '<h2>Inscrição realizada com sucesso!</h2>' +
            '<p>Bem-vindo à ACIC-DF! Sua inscrição foi recebida e estamos processando seu cadastro.</p>' +
            paymentHtml +
            '<div class="next-steps">' +
                '<h3>Próximos passos:</h3>' +
                '<ol>' +
                    '<li>Confirme o pagamento ' + (paymentMethod === 'pix' ? 'via PIX acima' : 'pelo método escolhido') + '</li>' +
                    '<li>Você receberá um e-mail de confirmação</li>' +
                    '<li>Acesse o portal do associado com o e-mail e senha cadastrados</li>' +
                    '<li>Aproveite todos os benefícios da associação!</li>' +
                '</ol>' +
            '</div>' +
            (result.gateway_url ? '<a href="' + escapeAttr(result.gateway_url) + '" target="_blank" class="btn-portal" style="margin-bottom:10px;background:var(--blue,#1B2B6B)">Abrir pagamento no Asaas &#8594;</a>' : '') +
            '<a href="' + PORTAL_URL + '" class="btn-portal">Acessar o portal &#8594;</a>';
    }

    // === HELPERS ===
    function showError(field) {
        var input = document.getElementById(field);
        var err = document.getElementById(field + '-error');
        if (input) input.classList.add('error');
        if (err) err.classList.add('show');
    }

    function clearErrors() {
        document.querySelectorAll('.error').forEach(function(el) { el.classList.remove('error'); });
        document.querySelectorAll('.error-msg').forEach(function(el) { el.classList.remove('show'); });
    }

    function formatMoney(v) {
        return parseFloat(v).toFixed(2).replace('.', ',');
    }

    function escapeHTML(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return str.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }


    // === SAVE/RESTORE DRAFT (localStorage) ===
    var DRAFT_KEY = 'acic_associe_draft';

    function saveDraft() {
        var fields = ['cnpj','razao_social','fantasia','cnae','telefone_empresa','email_empresa',
                      'segmento','cep','logradouro','numero','complemento','bairro','cidade','uf',
                      'nome','cpf','email','whatsapp'];
        var draft = {};
        fields.forEach(function(f) {
            var el = document.getElementById(f);
            if (el && el.value) draft[f] = el.value;
        });
        if (selectedPlan) draft._plano_id = selectedPlan.id;
        draft._step = currentStep;
        draft._saved = Date.now();
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    }

    function restoreDraft() {
        var raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        try {
            var draft = JSON.parse(raw);
            // Expirar draft apos 24h
            if (Date.now() - (draft._saved || 0) > 86400000) {
                localStorage.removeItem(DRAFT_KEY);
                return;
            }
            var fields = ['razao_social','fantasia','cnae','telefone_empresa','email_empresa',
                          'segmento','cep','logradouro','numero','complemento','bairro','cidade','uf',
                          'nome','cpf','email','whatsapp'];
            var hasData = fields.some(function(f) { return !!draft[f]; });
            if (!hasData) return;

            if (!confirm('Encontramos um rascunho salvo. Deseja restaurar?')) {
                localStorage.removeItem(DRAFT_KEY);
                return;
            }
            fields.forEach(function(f) {
                var el = document.getElementById(f);
                if (el && draft[f]) el.value = draft[f];
            });
            if (draft.cnpj) document.getElementById('cnpj').value = draft.cnpj;
            // Restaurar plano selecionado apos carregar planos
            if (draft._plano_id) {
                setTimeout(function() {
                    var card = document.querySelector('.plan-card[data-id="' + draft._plano_id + '"]');
                    if (card) card.click();
                }, 1500);
            }
        } catch(e) {}
    }

    function clearDraft() { localStorage.removeItem(DRAFT_KEY); }

    // Auto-save ao mudar de step
    var _origGoToStep = window.goToStep;
    window.goToStep = function(step) {
        if (step > 1) saveDraft();
        _origGoToStep(step);
    };

    // Init
    restoreDraft();
    loadPlans();

})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
