# Production image: static site + API in one Node process, SQLite on a mounted volume.
FROM node:22-alpine
WORKDIR /app
COPY package.json ./
COPY data ./data
COPY src ./src
COPY scripts ./scripts
COPY server ./server
RUN node scripts/build.mjs
ENV NODE_ENV=production PORT=4477 LUMEA_DATA_DIR=/data
VOLUME ["/data"]
EXPOSE 4477
HEALTHCHECK --interval=30s CMD wget -qO- http://127.0.0.1:4477/api/health || exit 1
CMD ["node", "server/index.mjs"]
