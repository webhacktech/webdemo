// This module provides a caching mechanism that first attempts to use Redis,
import dotenv from "dotenv";
import NodeCache from "node-cache";
import { createClient } from "redis";

dotenv.config();

const REDIS_URL = process.env.REDIS_URL || null;
const REDIS_PASSWORD = process.env.REDIS_PASSWORD || null;

let redisClient = null;
let redisAvailable = false;

if (REDIS_URL) {
  redisClient = createClient({
    url: REDIS_URL,
    password: REDIS_PASSWORD,
  });

  redisClient.on("error", (err) => {
    console.warn("Redis connection failed, falling back to local cache:", err.message);
  });

  redisClient.connect()
    .then(() => {
      redisAvailable = true;
      console.log("✅ Connected to Redis");
    })
    .catch(err => {
      console.warn("❌ Redis connection error:", err.message);
    });
}

// Create in-memory local cache
const localCache = new NodeCache({ stdTTL: 300 }); // default 5 min

export const getCachedData = async (key) => {
  if (redisAvailable && redisClient) {
    try {
      const value = await redisClient.get(key);
      return value ? JSON.parse(value) : null;
    } catch (err) {
      console.warn("Redis get error, falling back to local cache:", err.message);
    }
  }

  return localCache.get(key) || null;
};

export const setCachedData = async (key, value, ttl = 300) => {
  if (redisAvailable && redisClient) {
    try {
      await redisClient.set(key, JSON.stringify(value), { EX: ttl });
      return;
    } catch (err) {
      console.warn("Redis set error, falling back to local cache:", err.message);
    }
  }

  localCache.set(key, value, ttl);
};
export const deleteCachedData = async (key) => {
  if (redisAvailable && redisClient) {
    try {
      await redisClient.del(key);
    } catch (err) {
      console.warn("Redis delete error, falling back to local cache:", err.message);
    }
  }

  localCache.del(key);
};