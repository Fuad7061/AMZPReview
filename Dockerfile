# --- build stage ---
FROM node:20-alpine AS build
WORKDIR /app

# install deps first for better layer caching
COPY package.json package-lock.json* bun.lockb* ./
RUN npm install --no-audit --no-fund

# copy source & build (nitro node-server preset -> dist/server + dist/client)
COPY . .
ENV NITRO_PRESET=node-server
RUN npm run build

# --- runtime stage ---
FROM node:20-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production
ENV PORT=3000
ENV HOST=0.0.0.0

# copy only what the server needs
COPY --from=build /app/dist ./dist
COPY --from=build /app/package.json ./package.json

EXPOSE 3000

# nitro node-server preset writes the entry to dist/server/index.mjs
CMD ["node", "dist/server/index.mjs"]
