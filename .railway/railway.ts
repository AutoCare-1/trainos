import { defineRailway, github, mysql, project, service } from "railway/iac";

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
    // O Dockerfile e o railway.toml de backend-laravel cuidam do build; aqui
    // fica só o que é do ambiente.
    healthcheckPath: "/up",
    healthcheckTimeout: 300,
    env: {
      APP_ENV: "production",
      APP_DEBUG: "false",
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
    },
  });

  const frontend = service("frontend", {
    source: github("AutoCare-1/trainos", { rootDirectory: "frontend" }),
    env: {
      // Precisa existir no BUILD, não só em runtime: o Next.js embute
      // NEXT_PUBLIC_* no bundle na hora de compilar.
      NEXT_PUBLIC_API_URL: `https://${backend.env.RAILWAY_PUBLIC_DOMAIN}`,
      NODE_ENV: "production",
    },
  });

  // O CORS do Laravel lê FRONTEND_URL (config/cors.php). Ficar aqui embaixo, e
  // não no bloco do backend, é o que quebra o ciclo: os dois serviços já
  // existem quando esta linha é resolvida.
  backend.env.FRONTEND_URL = `https://${frontend.env.RAILWAY_PUBLIC_DOMAIN}`;

  return project("trainos", {
    resources: [banco, backend, frontend],
  });
});
