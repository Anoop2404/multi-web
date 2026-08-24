FROM node:22-slim

# System Chromium + metric-compatible Arial/Times New Roman substitutes for the
# certificate template font stacks (see CertificateTemplate::fontFamilyOptions()).
# Using the distro's Chromium (via puppeteer-core + PUPPETEER_EXECUTABLE_PATH below)
# instead of letting Puppeteer download its own avoids a second, redundant Chromium
# binary and keeps the image reproducible from apt's own pinned versions.
# Add regional script font packages here if student/school names ever need non-Latin
# glyphs (e.g. fonts-noto-sans-malayalam) — none of the configured template fonts do.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        chromium \
        fonts-liberation \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    NODE_ENV=production

WORKDIR /app

COPY services/pdf-renderer/package.json ./
RUN npm install --omit=dev

COPY services/pdf-renderer/server.js ./

EXPOSE 3000

CMD ["node", "server.js"]
