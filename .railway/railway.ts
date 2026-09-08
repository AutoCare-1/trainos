import { defineRailway, github, mysql, preserve, project, service } from "railway/iac";

/**
 * Infraestrutura do TrainOS no Railway, descrita em código.
 *
 * Por que assim e não clicando no painel: `railway config plan` mostra
 * exatamente o que vai ser criado ANTES de criar, e o arquivo fica versionado
 * — daqui a três meses dá pra saber por que uma variável existe.
 *
 * Os dois serviços saem do MESMO repositório (é um monorepo), cada um com seu
 * rootDirectory. Sem isso o Railway tenta buildar a raiz e falha.
 */
export default defineRailway(() => {
  const banco = mysql("MySQL");

  const backend = service("backend", {
    source: github("AutoCare-1/trainos", { rootDirectory: "backend-laravel" }),
    // O build sai do Dockerfile de backend-laravel; o railway.toml de lá diz
    // só isso. Todo o resto do deploy é decidido AQUI, de propósito: ter os
    // mesmos campos nos dois arquivos com valores diferentes é pedir pra um
    // silenciosamente vencer o outro.
    deploy: {
      healthcheckPath: "/up",
      // O entrypoint faz migrations e semeia exercícios, notificações e o
      // catálogo de alimentos ANTES do Apache subir — medido em 4s num banco
      // vazio, mas é a única janela que existe pra isso e ela não pode ser
      // apertada. 30s (o valor que estava no .toml) não deixa margem pra um
      // banco que ainda está acordando no primeiro deploy.
      healthcheckTimeout: 300,
      restartPolicyType: "ON_FAILURE",
      restartPolicyMaxRetries: 3,
    },
    env: {
      APP_ENV: "production",
      APP_DEBUG: "false",

      // Gerados uma vez e gravados direto no Railway — nunca passaram por
      // arquivo versionado nem por conversa. preserve() diz "existe, mantém o
      // valor": sem isso o `apply` entende que variável fora do arquivo é
      // variável pra apagar, e APAGARIA os dois. Sem APP_KEY e JWT_SECRET o
      // Laravel não sobe e todo mundo perde a sessão.
      APP_KEY: preserve(),
      JWT_SECRET: preserve(),

      // O Apache escuta nesta porta (ver docker/entrypoint.sh) e o domínio
      // público aponta pra ela. Fixa nos dois lados de propósito: quando o
      // valor injetado pelo Railway não batia com o do domínio, tudo respondia
      // 502 com o serviço marcado como "Online".
      PORT: "80",
      APP_URL: "https://${{RAILWAY_PUBLIC_DOMAIN}}",
      LOG_CHANNEL: "stderr",
      LOG_LEVEL: "warning",

      DB_CONNECTION: "mysql",
      DB_HOST: banco.env.MYSQLHOST,
      DB_PORT: banco.env.MYSQLPORT,
      DB_DATABASE: banco.env.MYSQLDATABASE,
      DB_USERNAME: banco.env.MYSQLUSER,
      DB_PASSWORD: banco.env.MYSQLPASSWORD,

      // Sessão e cache em arquivo: o container é efêmero, mas nada aqui depende
      // de sessão sobreviver a um deploy (a autenticação é por JWT).
      SESSION_DRIVER: "file",
      CACHE_STORE: "file",
      QUEUE_CONNECTION: "sync",

      // O CORS do Laravel lê daqui (config/cors.php). Escrito na sintaxe de
      // referência do próprio Railway, e não como referência tipada, porque os
      // dois serviços apontam um pro outro: em TypeScript isso é um ciclo que
      // não compila, e no Railway é só um nome resolvido na hora do deploy.
      FRONTEND_URL: "https://${{frontend.RAILWAY_PUBLIC_DOMAIN}}",
    },
  });

  const frontend = service("frontend", {
    source: github("AutoCare-1/trainos", { rootDirectory: "frontend" }),
    env: {
      // Precisa existir no BUILD, não só em runtime: o Next.js embute
      // NEXT_PUBLIC_* no bundle na hora de compilar.
      NEXT_PUBLIC_API_URL: "https://${{backend.RAILWAY_PUBLIC_DOMAIN}}",
      NODE_ENV: "production",
    },
  });

  // ATENÇÃO no primeiro apply: as duas variáveis acima dependem de cada serviço
  // ter um domínio público. Se o Railway não gerar sozinho, elas resolvem pra
  // "https://" vazio — e o sintoma é o app abrir e nenhuma chamada funcionar.
  // Conserto: "Generate Domain" nos dois serviços e redeploy (o frontend PRECISA
  // ser reconstruído, porque NEXT_PUBLIC_* entra no bundle na hora do build).
  return project("trainos", {
    resources: [banco, backend, frontend],
  });
});
