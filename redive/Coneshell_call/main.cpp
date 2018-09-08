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
	//auto h = Wrap(LoadLibraryA("E:/Game/DMM/priconner/PrincessConnectReDive_Data/Plugins/coneshell.dll"), &FreeLibrary);
	auto h = Wrap(LoadLibraryA("coneshell.dll"), &FreeLibrary);
	using Int = std::int32_t;
	using Long = std::int64_t;
	using IntPtr = void *;
	using ByteArray = char *;
	auto *_fx00 = (IntPtr(*)()) GetProcAddress(h.get(), "_fx00");
	auto *_a = (IntPtr(*)()) _fx00();                                 // 获取函数指针
	auto *_e = (Int(*)(IntPtr, ByteArray, Int, ByteArray, Int)) _a(); // Pack(IntPtr out, ByteArray body, int bodyLen, ByteArray iv, int unk)
	auto *_g = (Int(*)(IntPtr, ByteArray, Int)) _a();                 // Unpack(IntPtr out, ByteArray body, int bodyLen)
	auto *_h = (Int(*)(IntPtr, Int, IntPtr, Int)) _a();               // DecompressUnpacked(IntPtr out, int decompressedSize, IntPtr body, int bodyLen)
	auto *_c = (void(*)()) _a();                                      // ResetContext()
	auto *_i = (IntPtr(*)(ByteArray, Long, ByteArray)) _a();          // OpenCustomVFS(ByteArray cdb, int cdbSize, ByteArray dbName)
	auto *_j = (void(*)(IntPtr)) _a();                                // CloseVFS(IntPtr vfsHandle)
	auto *_b = (Int(*)(ByteArray, ByteArray)) _a();                   // InitializeContext(udid, key)
	auto *_d = (Int(*)(Int, Int)) _a();                               // GetPackedSize(int bodySize)
	auto *_f = (Int(*)(Int)) _a();                                    // GetUnpackedSize(int bodySize)

	if (argc < 4) {
		cerr << endl << "Not enough param" << endl
			<< "\t-cdb\t\t<in> <out>\tunpack cdb" << endl
		Sleep(3e3);
		return 1;
	}
	string mode = argv[1];
	if (mode == "-cdb") {
		auto  cdb = ReadAll(argv[2]);
		char name[]{ "master.mdb" };
		//pre key transformation
		if (cdb[3] == 3) {
			uint8_t *cdbChar = (uint8_t*)cdb.data();
			uint64_t v12 = 0;
			uint64_t v13, v14, v15, v16;
			uint8_t v109[16];
			v13 = 2
				* ((((((((unsigned __int64)cdbChar[32] << 56) & 0xFF00FFFFFFFFFFFFLL | ((unsigned __int64)cdbChar[33] << 48)) & 0xFFFF00FFFFFFFFFFLL | ((unsigned __int64)cdbChar[34] << 40)) & 0xFFFFFF00FFFFFFFFLL | ((unsigned __int64)cdbChar[35] << 32)) & 0xFFFFFFFF00FFFFFFLL | ((unsigned __int64)cdbChar[36] << 24)) & 0xFFFFFFFFFF00FFFFLL | ((unsigned __int64)cdbChar[37] << 16)) & 0xFFFFFFFFFFFF00FFLL | ((unsigned __int64)cdbChar[38] << 8) | cdbChar[39]) | 1;
			v14 = v13
				+ 0x5851F42D4C957F2DLL
				* (v13
					+ ((((((((unsigned __int64)cdbChar[24] << 56) & 0xFF00FFFFFFFFFFFFLL | ((unsigned __int64)cdbChar[25] << 48)) & 0xFFFF00FFFFFFFFFFLL | ((unsigned __int64)cdbChar[26] << 40)) & 0xFFFFFF00FFFFFFFFLL | ((unsigned __int64)cdbChar[27] << 32)) & 0xFFFFFFFF00FFFFFFLL | ((unsigned __int64)cdbChar[28] << 24)) & 0xFFFFFFFFFF00FFFFLL | ((unsigned __int64)cdbChar[29] << 16)) & 0xFFFFFFFFFFFF00FFLL | ((unsigned __int64)cdbChar[30] << 8) | cdbChar[31]));
			v15 = 2
				* ((((((((unsigned __int64)cdbChar[12] << 56) & 0xFF00FFFFFFFFFFFFLL | ((unsigned __int64)cdbChar[13] << 48)) & 0xFFFF00FFFFFFFFFFLL | ((unsigned __int64)cdbChar[14] << 40)) & 0xFFFFFF00FFFFFFFFLL | ((unsigned __int64)cdbChar[15] << 32)) & 0xFFFFFFFF00FFFFFFLL | ((unsigned __int64)cdbChar[16] << 24)) & 0xFFFFFFFFFF00FFFFLL | ((unsigned __int64)cdbChar[17] << 16)) & 0xFFFFFFFFFFFF00FFLL | ((unsigned __int64)cdbChar[18] << 8) | cdbChar[19]) | 1;
			v16 = v15
				+ 0x5851F42D4C957F2DLL
				* (v15
					+ ((((((((unsigned __int64)cdbChar[4] << 56) & 0xFF00FFFFFFFFFFFFLL | ((unsigned __int64)cdbChar[5] << 48)) & 0xFFFF00FFFFFFFFFFLL | ((unsigned __int64)cdbChar[6] << 40)) & 0xFFFFFF00FFFFFFFFLL | ((unsigned __int64)cdbChar[7] << 32)) & 0xFFFFFFFF00FFFFFFLL | ((unsigned __int64)cdbChar[8] << 24)) & 0xFFFFFFFFFF00FFFFLL | ((unsigned __int64)cdbChar[9] << 16)) & 0xFFFFFFFFFFFF00FFLL | ((unsigned __int64)cdbChar[10] << 8) | cdbChar[11]));
			do
			{
				uint64_t v17 = v13 + 0x5851F42D4C957F2DLL * v14;
				uint64_t v18 = _lrotr((v14 ^ (v14 >> 18)) >> 27, v14 >> 59);
				uint64_t v19 = _lrotr((v16 ^ (v16 >> 18)) >> 27, v16 >> 59);
				v109[v12] = v18 ^ v19;
				uint64_t v20 = 0LL;
				uint64_t v21 = 1LL;
				if (v19)
				{
					uint64_t v22 = v13;
					uint64_t v23 = 6364136223846793005LL;
					v19 = (unsigned int)v19;
					do
					{
						if (v19 & 1)
							v21 *= v23;
						if (v19 & 1)
							v20 = v22 + v23 * v20;
						v22 *= v23 + 1;
						v23 *= v23;
						v19 >>= 1;
					} while (v19);
				}
				uint64_t v24 = v15 + 0x5851F42D4C957F2DLL * v16;
				v14 = v20 + v21 * v17;
				uint64_t v25 = 0LL;
				uint64_t v26 = 1LL;
				if (v18)
				{
					uint64_t v27 = v15;
					uint64_t v28 = 0x5851F42D4C957F2DLL;
					v18 = (unsigned int)v18;
					do
					{
						if (v18 & 1)
							v26 *= v28;
						if (v18 & 1)
							v25 = v27 + v28 * v25;
						v27 *= v28 + 1;
						v28 *= v28;
						v18 >>= 1;
					} while (v18);
				}
				v16 = v25 + v26 * v24;
				++v12;
			} while (v12 != 16);
			int i = 0;
			do {
				cdbChar[0x3c + i] = cdbChar[20 + i];
				i++;
			} while (i != 8);
			i = 0;
			do {
				cdbChar[8 + i] = v109[i];
				++i;
			} while (i != 16);
			//cdbChar[3] = 2;
		}
		else {
			return -1;
		}
		// prepare cdb to vfs
		auto vfs = _i((ByteArray)cdb.data(), (Long)cdb.size(), name);
		int* dbSize = (int*)((char*)vfs + 0x58);
		char* dbData = (char*)vfs + 0x1000;
		WriteAll(argv[3], dbData, *dbSize);
		return 0;

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
	else {
		cerr << endl << "Not recognized param" << endl
			<< "\t-cdb\t\t<in> <out>\tunpack cdb" << endl
		return 1;
	}
}
