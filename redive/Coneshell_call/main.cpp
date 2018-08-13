/**
 * coneshell.dll caller
 * @author EAirPeter & esterTion
 * coneshell.dll is from (c)Cygames Inc.
 */
#define _CRT_SECURE_NO_WARNINGS
#define _WIN32_LEAN_AND_MEAN

#include <cstdint>
#include <memory>
#include <string>
#include <utility>
#include <iostream>
#include <sstream>
using namespace std;

#include <Windows.h>

#include "sqlite3.h"

template<class Obj, class Del>
decltype(auto) Wrap(Obj *ptr, Del del) {
	return std::unique_ptr<Obj, Del>(ptr, del);
}
std::string ReadAll(const char *file) {
	std::string res;
	auto f = Wrap(fopen(file, "rb"), &fclose);
	char buf[4096];
	while (!feof(f.get()))
		res.append(buf, fread(buf, 1, sizeof(buf), f.get()));
	return res;
}
void WriteAll(const char *file, const char* data, int length) {
	auto f = Wrap(fopen(file, "wb"), &fclose);
	int wrote = 0;
	int nextChunk = 4096;
	while (wrote < length) {
		wrote += 4096;
		if (wrote > length) {
			nextChunk -= wrote - length;
		}
		fwrite(data + wrote - 4096, 1, nextChunk, f.get());
	}
}
string hex2bin(string hex) {
	if (hex.length() % 2 != 0) {
		return NULL;
	}
	string val = "";
	int len = hex.length();
	for (int i = 0; i < len; i += 2) {
		char byte = 0;
		char c;
		c = hex[i];
		if (c >= '0' && c <= '9') byte = (c - '0') << 4;
		else if (c >= 'A' && c <= 'F') byte = (c - 'A' + 10) << 4;
		else if (c >= 'a' && c <= 'f') byte = (c - 'a' + 10) << 4;
		else return NULL;

		c = hex[i + 1];
		if (c >= '0' && c <= '9') byte |= (c - '0');
		else if (c >= 'A' && c <= 'F') byte |= (c - 'A' + 10);
		else if (c >= 'a' && c <= 'f') byte |= (c - 'a' + 10);
		else return NULL;
		val += byte;
	}
	return val;
}

int main(int argc, const char* argv[]) {
	auto h = Wrap(LoadLibraryA("coneshell.dll"), &FreeLibrary);
	using Int = std::int32_t;
	using Long = std::int64_t;
	using IntPtr = void *;
	using ByteArray = char *;
	auto *_fx00 = (IntPtr(*)()) GetProcAddress(h.get(), "_fx00");
	auto *_a = (IntPtr(*)()) _fx00(); // 获取函数指针
	auto *_e = (Int(*)(IntPtr, ByteArray, Int, ByteArray, Int)) _a(); // Pack(IntPtr out, ByteArray body, int bodyLen, ByteArray iv, int unk)
	auto *_g = (Int(*)(IntPtr, ByteArray, Int)) _a(); // Unpack(IntPtr out, ByteArray body, int bodyLen)
	auto *_h = (Int(*)(IntPtr, Int, IntPtr, Int)) _a(); // DecompressUnpacked(IntPtr out, int decompressedSize, IntPtr body, int bodyLen)
	auto *_c = (void(*)()) _a(); // ResetContext()
	auto *_i = (IntPtr(*)(ByteArray, Long, ByteArray)) _a(); // OpenCustomVFS(ByteArray cdb, int cdbSize, ByteArray dbName)
	auto *_j = (void(*)(IntPtr)) _a(); // CloseVFS(IntPtr vfsHandle)
	auto *_b = (Int(*)(ByteArray, ByteArray)) _a(); // InitializeContext(udid, key)
	auto *_d = (Int(*)(Int, Int)) _a(); // GetPackedSize(int bodySize)
	auto *_f = (Int(*)(Int)) _a(); // GetUnpackedSize(int bodySize)

	if (argc < 4) {
		cerr << endl << "Not enough param" << endl
			<< "\t-cdb\t\t<in> <out>\tunpack cdb" << endl
			<< "\t-pack-<udid>\t<in> <out>\tpack request body from json" << endl
			<< "\t-unpack-<udid>\t<in> <out>\tunpack response body to json" << endl;
		Sleep(3e3);
		return 1;
	}
	string mode = argv[1];
	if (mode == "-cdb") {
		auto  cdb = ReadAll(argv[2]);
		char name[]{ "master.mdb" };
		// prepare cdb to vfs
		auto vfs = _i((ByteArray)cdb.data(), (Long)cdb.size(), name);
		auto res = sqlite3_vfs_register((sqlite3_vfs *)vfs, 0);
		if (res)
			return res;
		sqlite3 *psrc;
		res = sqlite3_open_v2(name, &psrc, SQLITE_OPEN_READONLY, name);
		if (res)
			return res;
		auto src = Wrap(psrc, &sqlite3_close);
		sqlite3 *pdst;
		res = sqlite3_open(argv[3], &pdst);
		if (res)
			return res;
		auto dst = Wrap(pdst, &sqlite3_close);
		auto pbk = sqlite3_backup_init(pdst, "main", psrc, "main");
		if (!pbk)
			return -1;
		auto bk = Wrap(pbk, &sqlite3_backup_finish);
		res = sqlite3_backup_step(pbk, -1);
		if (res != SQLITE_DONE)
			return res;
		return 0;
	}
	else if (mode.substr(0, 7) == "-unpack") {
		//string udid = "edcadba12a674a089107d8065a031742";
		string udid = mode.substr(8);
		string udidHex = hex2bin(udid);
		int res0 = _b((ByteArray)udidHex.c_str(), "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");

		// pack once before unpack
		char body[] = { "{}" };
		int packedLen = _d(strlen(body), 0);
		ByteArray packed = (ByteArray)malloc(packedLen);
		int res = _e(packed, body, strlen(body), "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0", 0);
		free(packed);

		// unpack
		auto encrypted = ReadAll(argv[2]);
		auto unpackedLen = _f(encrypted.size());
		IntPtr unpacked = (IntPtr)malloc(unpackedLen);
		memset(unpacked, 0, unpackedLen);
		res = _g(unpacked, (ByteArray)encrypted.data(), encrypted.size());
		if (res < 0) {
			return 2;
		}

		// uncompress?
		char* output;
		int outputSize;
		IntPtr json = NULL;
		unsigned int uncompressedSize = *(char*)unpacked + (*((char*)unpacked + 1) << 8) + (*((char*)unpacked + 2) << 16) + (*((char*)unpacked + 3) << 24);
		if (uncompressedSize > 0) {
			json = (IntPtr)malloc(uncompressedSize);
			int res2 = _h(json, uncompressedSize, (char*)unpacked + 4, unpackedLen - 4);
			if (res2 < 0) {
				return 2;
			}
			output = (char*)json;
			outputSize = res2;
		}
		else {
			output = (char*)unpacked + 4;
			outputSize = unpackedLen - 4;
		}
		WriteAll(argv[3], output, outputSize);
		free(unpacked);
		if (json != NULL) free(json);
		return 0;
	}
	else if (mode.substr(0, 5) == "-pack") {
		auto body = ReadAll(argv[2]);
		//string udid = "edcadba12a674a089107d8065a031742";
		string udid = mode.substr(6);
		string udidHex = hex2bin(udid);
		_b((ByteArray)udidHex.c_str(), "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
		int packedLen = _d(body.size(), 0);
		ByteArray packed = (ByteArray)malloc(packedLen);
		int res = _e(packed, (ByteArray)body.c_str(), body.size(), "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0", 0);
		if (res > 0)
		WriteAll(argv[3], packed, res);
		free(packed);
	}
	else {
		cerr << endl << "Not recognized param" << endl
			<< "\t-cdb\t\t<in> <out>\tunpack cdb" << endl
			<< "\t-pack-<udid>\t<in> <out>\tpack request body from json" << endl
			<< "\t-unpack-<udid>\t<in> <out>\tunpack response body to json" << endl;
		return 1;
	}
}
