const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const PID_FILE = path.join(__dirname, '..', '.server.pid');
const PORT = Number(process.env.PORT || 3001);

function cleanupPidFile() {
  if (fs.existsSync(PID_FILE)) {
    fs.unlinkSync(PID_FILE);
  }
}

function isRunning(pid) {
  try {
    process.kill(pid, 0);
    return true;
  } catch (_err) {
    return false;
  }
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function getListeningPidFromPort(port) {
  const commands = [
    `lsof -t -iTCP:${port} -sTCP:LISTEN`,
    `ss -ltnp 'sport = :${port}'`,
  ];

  for (const cmd of commands) {
    try {
      const output = execSync(cmd, { stdio: ['ignore', 'pipe', 'ignore'] })
        .toString()
        .trim();

      if (!output) {
        continue;
      }

      if (cmd.startsWith('lsof')) {
        const pid = Number(output.split('\n')[0].trim());
        if (Number.isInteger(pid) && pid > 0) {
          return pid;
        }
        continue;
      }

      const lines = output.split('\n');
      for (const line of lines) {
        const match = line.match(/pid=(\d+)/);
        if (match) {
          const pid = Number(match[1]);
          if (Number.isInteger(pid) && pid > 0) {
            return pid;
          }
        }
      }
    } catch (_err) {
      // Try the next command if this one is unavailable or returns no matches.
    }
  }

  return null;
}

async function stopByPid(pid) {
  if (!isRunning(pid)) {
    return false;
  }

  console.log(`Stopping server PID ${pid}...`);
  process.kill(pid, 'SIGTERM');

  const deadlineMs = 5000;
  const intervalMs = 100;
  let waited = 0;

  while (waited < deadlineMs) {
    if (!isRunning(pid)) {
      console.log('Server stopped successfully');
      return true;
    }
    await sleep(intervalMs);
    waited += intervalMs;
  }

  console.log('Graceful shutdown timed out; sending SIGKILL');
  process.kill(pid, 'SIGKILL');
  console.log('Server force-stopped');
  return true;
}

async function stopServer() {
  if (!fs.existsSync(PID_FILE)) {
    const fallbackPid = getListeningPidFromPort(PORT);
    if (!fallbackPid) {
      console.log('Server is not running (no PID file found)');
      return;
    }

    console.log(`PID file not found; stopping process listening on port ${PORT}`);
    await stopByPid(fallbackPid);
    return;
  }

  const raw = fs.readFileSync(PID_FILE, 'utf8').trim();
  const pid = Number(raw);

  if (!Number.isInteger(pid) || pid <= 0) {
    cleanupPidFile();
    throw new Error(`Invalid PID file contents: "${raw}"`);
  }

  if (!isRunning(pid)) {
    console.log(`Server PID ${pid} is not running; cleaning stale PID file`);
    cleanupPidFile();
    const fallbackPid = getListeningPidFromPort(PORT);
    if (fallbackPid) {
      console.log(`Stopping process listening on port ${PORT}`);
      await stopByPid(fallbackPid);
    }
    return;
  }

  await stopByPid(pid);
  cleanupPidFile();
}

stopServer().catch((err) => {
  console.error('Failed to stop server:', err.message);
  process.exit(1);
});
