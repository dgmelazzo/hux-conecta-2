<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Associe-se — ACIC Conecta</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --orange: #E8701A;
            --orange-hover: #d06316;
            --orange-light: rgba(232,112,26,0.08);
            --blue: #1B2B6B;
            --blue-light: rgba(27,43,107,0.06);
            --bg: #F0F2F5;
            --white: #FFFFFF;
            --gray-100: #F7F8FA;
            --gray-200: #E4E7EC;
            --gray-300: #C4C9D4;
            --gray-500: #6B7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --red: #DC2626;
            --green: #16A34A;
            --radius: 12px;
            --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1), 0 4px 10px rgba(0,0,0,0.06);
        }

        body {
            font-family: 'Montserrat', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--gray-900);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* NAV */
        .nav {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            padding: 0 24px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .nav-logo {
            font-size: 20px;
            font-weight: 700;
            color: var(--blue);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-logo span { color: var(--orange); }

        .nav-login {
            color: var(--blue);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 20px;
            border: 2px solid var(--blue);
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-login:hover {
            background: var(--blue);
            color: var(--white);
        }

        /* CONTAINER */
        .container {
            max-width: 860px;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }

        /* HERO */
        .hero {
            text-align: center;
            margin-bottom: 40px;
        }

        .hero h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--blue);
            margin-bottom: 8px;
        }

        .hero p {
            color: var(--gray-500);
            font-size: 16px;
            margin-bottom: 32px;
        }

        /* PROGRESS BAR — 4 steps */
        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: 40px;
        }

        .step-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .step-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            background: var(--gray-200);
            color: var(--gray-500);
            transition: all 0.3s;
            flex-shrink: 0;
            cursor: default;
        }

        .step-circle.active {
            background: var(--orange);
            color: var(--white);
            box-shadow: 0 0 0 4px rgba(232,112,26,0.18);
        }

        .step-circle.done {
            background: var(--green);
            color: var(--white);
            cursor: pointer;
        }

        .step-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-500);
            margin-top: 6px;
            text-align: center;
            width: 80px;
        }

        .step-label.active { color: var(--orange); }
        .step-label.done { color: var(--green); }

        .step-connector {
            width: 56px;
            height: 3px;
            background: var(--gray-200);
            margin: 0 4px;
            margin-bottom: 22px;
            border-radius: 2px;
            transition: background 0.3s;
        }

        .step-connector.done { background: var(--green); }

        /* SECTIONS */
        .section {
            background: var(--white);
            border-radius: var(--radius);
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            animation: fadeInUp 0.35s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--blue);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title .icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .wizard-step { display: none; }
        .wizard-step.active { display: block; }

        /* PLAN CARDS */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .plan-card {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 28px 24px;
            cursor: pointer;
            transition: all 0.25s;
            position: relative;
            text-align: center;
        }

        .plan-card:hover {
            border-color: var(--orange);
            box-shadow: var(--shadow-lg);
            transform: translateY(-3px);
        }

        .plan-card.selected {
            border-color: var(--orange);
            background: var(--orange-light);
            box-shadow: 0 0 0 3px rgba(232,112,26,0.14);
        }

        .plan-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--orange);
            color: var(--white);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 14px;
            border-radius: 20px;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plan-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 12px;
        }

        .plan-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--blue);
            margin-bottom: 4px;
        }

        .plan-price {
            font-size: 30px;
            font-weight: 800;
            color: var(--orange);
            margin: 12px 0 4px;
        }

        .plan-price small {
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-500);
        }

        .plan-desc {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 8px;
            line-height: 1.5;
        }

        .plan-select-btn {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 28px;
            background: var(--orange);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .plan-select-btn:hover { background: var(--orange-hover); }

        .plan-card.selected .plan-select-btn {
            background: var(--green);
        }

        .plans-loading {
            text-align: center;
            padding: 40px;
            color: var(--gray-500);
        }

        .plans-loading .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--orange);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 12px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* FORM */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-row.single { grid-template-columns: 1fr; }
        .form-row.triple { grid-template-columns: 1fr 1fr 1fr; }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .form-group label .required { color: var(--red); }

        .form-group input,
        .form-group select {
            height: 46px;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            padding: 0 14px;
            font-size: 15px;
            color: var(--gray-900);
            background: var(--white);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(232,112,26,0.1);
        }

        .form-group input.error,
        .form-group select.error {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }

        .form-group input.valid {
            border-color: var(--green);
        }

        .form-group input:disabled,
        .form-group input[readonly] {
            background: var(--gray-100);
            color: var(--gray-500);
            cursor: not-allowed;
        }

        .error-msg {
            font-size: 12px;
            color: var(--red);
            margin-top: 4px;
            display: none;
        }

        .error-msg.show { display: block; }

        .situacao-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 6px;
        }

        .situacao-badge.ativa {
            background: rgba(22,163,74,0.1);
            color: var(--green);
        }

        .situacao-badge.irregular {
            background: rgba(220,38,38,0.1);
            color: var(--red);
        }

        /* PASSWORD STRENGTH */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            background: var(--gray-200);
            margin-top: 6px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
        }

        .password-hint {
            font-size: 11px;
            color: var(--gray-500);
            margin-top: 4px;
        }

        /* NAVIGATION BUTTONS */
        .step-nav {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn-back {
            flex: 0 0 auto;
            height: 50px;
            padding: 0 28px;
            background: var(--white);
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back:hover {
            border-color: var(--gray-300);
            background: var(--gray-100);
        }

        .btn-next {
            flex: 1;
            height: 50px;
            background: var(--orange);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-next:hover { background: var(--orange-hover); transform: translateY(-1px); box-shadow: var(--shadow-lg); }
        .btn-next:active { transform: translateY(0); }
        .btn-next:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* PAYMENT */
        .payment-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
        }

        .payment-tab {
            flex: 1;
            padding: 14px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            cursor: pointer;
            background: var(--white);
            transition: all 0.2s;
            color: var(--gray-700);
        }

        .payment-tab:hover { border-color: var(--orange); }

        .payment-tab.active {
            border-color: var(--orange);
            background: var(--orange-light);
            color: var(--orange);
        }

        .summary-box {
            background: var(--gray-100);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            padding: 6px 0;
            color: var(--gray-700);
        }

        .summary-row.total {
            border-top: 2px solid var(--gray-200);
            margin-top: 8px;
            padding-top: 12px;
            font-weight: 700;
            font-size: 18px;
            color: var(--blue);
        }

        /* CONFIRMATION */
        .confirmation {
            text-align: center;
            padding: 20px 0;
        }

        .confirmation .check-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(22,163,74,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }

        .confirmation h2 {
            font-size: 24px;
            font-weight: 800;
            color: var(--blue);
            margin-bottom: 8px;
        }

        .confirmation p {
            color: var(--gray-500);
            font-size: 15px;
            max-width: 400px;
            margin: 0 auto 20px;
        }

        .confirmation .next-steps {
            background: var(--gray-100);
            border-radius: 10px;
            padding: 20px;
            margin: 20px auto;
            max-width: 420px;
            text-align: left;
        }

        .confirmation .next-steps h3 {
            font-size: 14px;
            font-weight: 700;
            color: var(--blue);
            margin-bottom: 12px;
        }

        .confirmation .next-steps li {
            font-size: 14px;
            color: var(--gray-700);
            margin-bottom: 8px;
            padding-left: 4px;
        }

        .btn-portal {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 52px;
            padding: 0 32px;
            background: var(--blue);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            text-decoration: none;
            margin-top: 12px;
        }

        .btn-portal:hover { opacity: 0.9; transform: translateY(-1px); }

        .qr-placeholder {
            width: 200px;
            height: 200px;
            margin: 16px auto;
            background: var(--gray-100);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: var(--gray-500);
        }

        .qr-placeholder img {
            max-width: 100%;
            max-height: 100%;
            border-radius: 12px;
        }

        .pix-code {
            font-size: 12px;
            word-break: break-all;
            background: var(--gray-100);
            padding: 12px;
            border-radius: 8px;
            max-width: 420px;
            margin: 12px auto;
            color: var(--gray-700);
        }

        .boleto-link {
            display: inline-block;
            background: var(--blue);
            color: var(--white);
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: opacity 0.2s;
        }

        .boleto-link:hover { opacity: 0.9; }

        /* FOOTER */
        .footer {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
            font-size: 13px;
            border-top: 1px solid var(--gray-200);
            margin-top: 40px;
        }

        .footer a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-selos {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 16px;
            font-size: 11px;
            color: var(--gray-300);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* RESPONSIVE */
        @media (max-width: 640px) {
            .container { padding: 20px 16px 60px; }
            .hero h1 { font-size: 24px; }
            .form-row, .form-row.triple { grid-template-columns: 1fr; }
            .section { padding: 20px; }
            .plans-grid { grid-template-columns: 1fr; }
            .step-connector { width: 24px; }
            .step-label { width: 60px; font-size: 9px; }
            .step-circle { width: 34px; height: 34px; font-size: 12px; }
            .step-nav { flex-direction: column-reverse; }
            .btn-back { justify-content: center; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
    <a href="/" class="nav-logo">ACIC <span>Conecta</span></a>
    <a href="https://conecta.acicdf.org.br" class="nav-login">Já é associado? Entrar</a>
</nav>

<!-- MAIN -->
<div class="container">

    <!-- HERO -->
    <div class="hero">
        <h1>Associe-se à ACIC-DF</h1>
        <p>Faça parte da maior rede de comércio do Distrito Federal</p>

        <div class="progress-bar" id="progress-bar">
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
        <div class="section">
            <div class="section-title">
                <div class="icon" style="background:var(--orange-light);color:var(--orange);">📋</div>
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
        <div class="section">
            <div class="section-title">
                <div class="icon" style="background:var(--blue-light);color:var(--blue);">🏢</div>
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

        <div class="section">
            <div class="section-title">
                <div class="icon" style="background:var(--blue-light);color:var(--blue);">📍</div>
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
                <button type="button" class="btn-back" onclick="goToStep(1)">&#8592; Voltar</button>
                <button type="button" class="btn-next" onclick="validateAndGoStep3()">Continuar &#8594;</button>
            </div>
        </div>
    </div>

    <!-- ============ STEP 3: RESPONSÁVEL LEGAL ============ -->
    <div class="wizard-step" id="wizard-step-3">
        <div class="section">
            <div class="section-title">
                <div class="icon" style="background:var(--blue-light);color:var(--blue);">👤</div>
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

            <div class="step-nav">
                <button type="button" class="btn-back" onclick="goToStep(2)">&#8592; Voltar</button>
                <button type="button" class="btn-next" onclick="validateAndGoStep4()">Continuar &#8594;</button>
            </div>
        </div>
    </div>

    <!-- ============ STEP 4: PAGAMENTO ============ -->
    <div class="wizard-step" id="wizard-step-4">
        <div class="section">
            <div class="section-title">
                <div class="icon" style="background:var(--orange-light);color:var(--orange);">💳</div>
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
                <button type="button" class="btn-back" onclick="goToStep(3)">&#8592; Voltar</button>
                <button type="button" class="btn-next" id="btn-submit" onclick="submitForm()">Finalizar inscrição &#8594;</button>
            </div>
        </div>
    </div>

    <!-- ============ STEP 5: CONFIRMAÇÃO ============ -->
    <div class="wizard-step" id="wizard-step-5">
        <div class="section">
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
            var res = await fetch('https://api-crm.acicdf.org.br/planos');
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
            var res = await fetch('https://api.cnpja.com.br/office/' + cnpj + '?simples=false');
            var data = await res.json();

            document.getElementById('cnpj-data').style.display = 'block';
            document.getElementById('razao_social').value = data.company ? data.company.name : (data.alias || '');
            document.getElementById('fantasia').value = data.alias || '';

            var cnaeText = '';
            if (data.mainActivity) cnaeText = data.mainActivity.id + ' - ' + data.mainActivity.text;
            else if (data.company && data.company.mainActivity) cnaeText = data.company.mainActivity.id + ' - ' + data.company.mainActivity.text;
            document.getElementById('cnae').value = cnaeText;

            var situacao = data.status ? data.status.text : (data.registration ? data.registration.status : '');
            cnpjSituacao = situacao.toUpperCase();
            var container = document.getElementById('situacao-container');

            if (cnpjSituacao === 'ATIVA') {
                container.innerHTML = '<div class="situacao-badge ativa">● ATIVA</div>';
            } else {
                container.innerHTML = '<div class="situacao-badge irregular">● ' + escapeHTML(cnpjSituacao || 'IRREGULAR') + '</div>';
            }

            // Auto-detect segmento from CNPJ data
            var porte = '';
            if (data.company && data.company.size) porte = data.company.size.text || '';
            if (!porte && data.company && data.company.simples && data.company.simples.mei) porte = 'MEI';
            var sel = document.getElementById('segmento');
            if (/mei/i.test(porte)) sel.value = 'MEI';
            else if (/micro/i.test(porte)) sel.value = 'ME';
            else if (/pequeno/i.test(porte)) sel.value = 'EPP';
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
            var res = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
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
            cnpj: document.getElementById('cnpj').value.replace(/\D/g,''),
            razao_social: document.getElementById('razao_social').value,
            fantasia: document.getElementById('fantasia').value,
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
            whatsapp: document.getElementById('whatsapp').value.replace(/\D/g,''),
            senha: document.getElementById('senha').value,
            metodo_pagamento: paymentMethod
        };

        try {
            var res = await fetch('https://api-crm.acicdf.org.br/inscricoes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            var result = await res.json();
            showConfirmation(result);
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
                    ? '<div class="pix-code">' + escapeHTML(result.pix_copia_cola) + '</div>'
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
            '<a href="https://conecta.acicdf.org.br" class="btn-portal">Acessar o portal &#8594;</a>';
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

    // Init
    loadPlans();

})();
</script>
</body>
</html>
